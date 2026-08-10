<?php

namespace App\Services;

use App\Enums\AgentCommand;
use App\Enums\ServerStatus;
use App\Enums\WebsiteStatus;
use App\Models\AgentInstallation;
use App\Models\Server;
use App\Models\ServerJob;
use App\Models\ServerMetric;
use App\Models\ServiceSnapshot;
use App\Models\User;
use App\Models\Website;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly JobService $jobService,
    ) {}

    /**
     * @return array{server: Server, plain_token: string, install_command: string}
     */
    public function createServer(User $user, string $name): array
    {
        $organization = $user->currentOrganization;

        abort_if($organization === null, 403);
        abort_unless($user->can(Permissions::SERVERS_CREATE), 403);

        return DB::transaction(function () use ($user, $organization, $name) {
            $server = Server::query()->create([
                'organization_id' => $organization->id,
                'name' => $name,
                'status' => ServerStatus::Pending,
            ]);

            $issued = AgentInstallation::issue($server, $user);

            $this->auditLogger->log(
                organization: $organization,
                action: 'server.created',
                user: $user,
                server: $server,
                payload: ['name' => $name],
                request: request(),
            );

            $baseUrl = rtrim((string) config('app.url'), '/');
            $installCommand = sprintf(
                'curl -fsSL %s/install/%s | sudo bash',
                $baseUrl,
                $issued['plain_token'],
            );

            return [
                'server' => $server,
                'plain_token' => $issued['plain_token'],
                'install_command' => $installCommand,
            ];
        });
    }

    /**
     * @return array{server: Server, agent_id: string, agent_secret: string}
     */
    public function register(string $plainToken, array $meta, ?string $ip = null): array
    {
        $prefix = substr($plainToken, 0, 8);

        $installation = AgentInstallation::query()
            ->with('server')
            ->where('token_prefix', $prefix)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->get()
            ->first(fn (AgentInstallation $item) => $item->verify($plainToken));

        if ($installation === null) {
            throw ValidationException::withMessages([
                'token' => 'Invalid or expired installation token.',
            ]);
        }

        $credentials = Server::generateAgentCredentials();

        return DB::transaction(function () use ($installation, $credentials, $meta, $ip) {
            $server = $installation->server;

            $server->setAgentSecret($credentials['agent_secret']);
            $server->fill([
                'agent_id' => $credentials['agent_id'],
                'hostname' => $meta['hostname'] ?? $server->hostname,
                'ip_address' => $meta['ip_address'] ?? $ip,
                'os_name' => $meta['os']['name'] ?? null,
                'os_version' => $meta['os']['version'] ?? null,
                'agent_version' => $meta['agent_version'] ?? null,
                'status' => ServerStatus::Online,
                'last_seen_at' => now(),
                'registered_at' => now(),
            ])->save();

            $installation->update([
                'used_at' => now(),
                'used_by_ip' => $ip,
            ]);

            $this->auditLogger->log(
                organization: $server->organization,
                action: 'agent.registered',
                server: $server,
                payload: ['hostname' => $server->hostname],
            );

            $this->jobService->dispatch($server, AgentCommand::DiscoverServer, priority: 90);
            $this->jobService->dispatch($server, AgentCommand::DiscoverWebsites, priority: 85);

            return [
                'server' => $server->fresh(),
                'agent_id' => $credentials['agent_id'],
                'agent_secret' => $credentials['agent_secret'],
            ];
        });
    }

    public function authenticate(string $agentId, string $agentSecret): Server
    {
        $server = Server::query()->where('agent_id', $agentId)->first();

        if ($server === null || ! $server->verifyAgentSecret($agentSecret)) {
            abort(401, 'AGENT_UNAUTHORIZED');
        }

        return $server;
    }

    public function heartbeat(Server $server, array $payload): Server
    {
        $server->fill([
            'hostname' => $payload['hostname'] ?? $server->hostname,
            'agent_version' => $payload['agent_version'] ?? $server->agent_version,
            'status' => ServerStatus::Online,
            'last_seen_at' => now(),
        ])->save();

        return $server;
    }

    public function storeDiscovery(Server $server, array $payload): Server
    {
        $server->fill([
            'os_name' => data_get($payload, 'os.name', $server->os_name),
            'os_version' => data_get($payload, 'os.version', $server->os_version),
            'cpu_cores' => data_get($payload, 'cpu.cores', $server->cpu_cores),
            'memory_total' => data_get($payload, 'memory.total', $server->memory_total),
            'disk_total' => data_get($payload, 'disk.total', $server->disk_total),
            'hostname' => $payload['hostname'] ?? $server->hostname,
            'discovery' => $payload,
            'last_seen_at' => now(),
            'status' => ServerStatus::Online,
        ])->save();

        if (isset($payload['services']) && is_array($payload['services'])) {
            foreach ($payload['services'] as $service) {
                if (! is_array($service) || blank($service['name'] ?? null)) {
                    continue;
                }

                ServiceSnapshot::query()->updateOrCreate(
                    [
                        'server_id' => $server->id,
                        'name' => $service['name'],
                    ],
                    [
                        'display_name' => $service['display_name'] ?? $service['name'],
                        'status' => $service['status'] ?? 'unknown',
                        'enabled' => (bool) ($service['enabled'] ?? false),
                        'version' => $service['version'] ?? null,
                        'meta' => $service['meta'] ?? null,
                        'checked_at' => now(),
                    ],
                );
            }
        }

        $this->auditLogger->log(
            organization: $server->organization,
            action: 'server.discovered',
            server: $server,
            payload: ['keys' => array_keys($payload)],
        );

        return $server;
    }

    public function syncWebsites(Server $server, array $websites): void
    {
        $seen = [];

        foreach ($websites as $item) {
            if (! is_array($item) || blank($item['domain'] ?? $item['primary_domain'] ?? null)) {
                continue;
            }

            $primary = $item['primary_domain'] ?? $item['domain'];
            $configPath = $item['config_path'] ?? null;

            $website = Website::query()->updateOrCreate(
                [
                    'server_id' => $server->id,
                    'primary_domain' => $primary,
                    'config_path' => $configPath,
                ],
                [
                    'name' => $item['name'] ?? $primary,
                    'domains' => $item['aliases'] ?? $item['domains'] ?? [],
                    'root_path' => $item['root_path'] ?? null,
                    'webserver' => $item['webserver'] ?? null,
                    'php_version' => $item['php_version'] ?? null,
                    'ssl_enabled' => (bool) ($item['ssl'] ?? $item['ssl_enabled'] ?? false),
                    'status' => WebsiteStatus::tryFrom($item['status'] ?? 'unknown') ?? WebsiteStatus::Unknown,
                    'framework' => $item['framework'] ?? null,
                    'framework_version' => $item['framework_version'] ?? null,
                    'meta' => $item['meta'] ?? null,
                    'detected_at' => now(),
                    'last_synced_at' => now(),
                ],
            );

            $seen[] = $website->id;
        }

        if ($seen !== []) {
            // Sites missing from sites-enabled discovery are treated as disabled, not unknown.
            Website::query()
                ->where('server_id', $server->id)
                ->whereNotIn('id', $seen)
                ->where('status', '!=', WebsiteStatus::Disabled)
                ->update(['status' => WebsiteStatus::Disabled, 'last_synced_at' => now()]);
        }

        $this->auditLogger->log(
            organization: $server->organization,
            action: 'websites.discovered',
            server: $server,
            payload: ['count' => count($seen)],
        );
    }

    public function applyWebsiteJobResult(ServerJob $job, bool $success): void
    {
        $website = $job->website;
        if ($website === null) {
            $websiteId = data_get($job->payload, 'website_id');
            if ($websiteId) {
                $website = Website::query()->find($websiteId);
            }
        }

        if ($website === null) {
            return;
        }

        if (! $success) {
            $website->forceFill([
                'status' => WebsiteStatus::Error,
                'last_synced_at' => now(),
            ])->save();

            return;
        }

        $status = match ($job->type) {
            AgentCommand::WebsiteStop, AgentCommand::WebsiteDisable => WebsiteStatus::Disabled,
            AgentCommand::WebsiteStart, AgentCommand::WebsiteEnable, AgentCommand::WebsiteRestart => WebsiteStatus::Active,
            default => null,
        };

        if ($status === null) {
            return;
        }

        $website->forceFill([
            'status' => $status,
            'last_synced_at' => now(),
        ])->save();
    }

    public function storeMetrics(Server $server, array $payload): ServerMetric
    {
        $metric = ServerMetric::query()->create([
            'server_id' => $server->id,
            'cpu_percent' => $payload['cpu_percent'] ?? null,
            'memory_percent' => $payload['memory_percent'] ?? null,
            'memory_used' => $payload['memory_used'] ?? null,
            'disk_percent' => $payload['disk_percent'] ?? null,
            'disk_used' => $payload['disk_used'] ?? null,
            'load_1' => data_get($payload, 'load.1') ?? data_get($payload, 'load_1'),
            'load_5' => data_get($payload, 'load.5') ?? data_get($payload, 'load_5'),
            'load_15' => data_get($payload, 'load.15') ?? data_get($payload, 'load_15'),
            'uptime_seconds' => $payload['uptime_seconds'] ?? null,
            'network' => $payload['network'] ?? null,
            'collected_at' => now(),
        ]);

        $server->forceFill(['last_seen_at' => now(), 'status' => ServerStatus::Online])->save();

        return $metric;
    }
}
