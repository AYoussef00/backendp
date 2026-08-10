<?php

namespace App\Http\Controllers\Web;

use App\Enums\AgentCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\StoreServerRequest;
use App\Models\Server;
use App\Services\AgentService;
use App\Services\AuditLogger;
use App\Services\JobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    public function __construct(
        private readonly AgentService $agentService,
        private readonly JobService $jobService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Server::class);

        $servers = Server::query()
            ->where('organization_id', $request->user()->current_organization_id)
            ->withCount('websites')
            ->with([
                'latestMetric',
                'websites' => fn ($query) => $query->orderBy('primary_domain'),
            ])
            ->latest()
            ->paginate(20)
            ->through(fn (Server $server) => [
                'id' => $server->id,
                'name' => $server->name,
                'hostname' => $server->hostname,
                'status' => $server->status->value,
                'websites_count' => $server->websites_count,
                'os' => trim(($server->os_name ?? '').' '.($server->os_version ?? '')),
                'cpu' => $server->latestMetric?->cpu_percent,
                'ram' => $server->latestMetric?->memory_percent,
                'disk' => $server->latestMetric?->disk_percent,
                'last_seen_at' => $server->last_seen_at?->toIso8601String(),
                'agent_version' => $server->agent_version,
                'websites' => $server->websites->map(fn ($website) => [
                    'id' => $website->id,
                    'primary_domain' => $website->primary_domain,
                    'status' => $website->status->value,
                    'webserver' => $website->webserver,
                    'php_version' => $website->php_version,
                    'ssl_enabled' => $website->ssl_enabled,
                ]),
            ]);

        return Inertia::render('Servers/Index', [
            'servers' => $servers,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Server::class);

        return Inertia::render('Servers/Create');
    }

    public function store(StoreServerRequest $request): RedirectResponse
    {
        $this->authorize('create', Server::class);

        $result = $this->agentService->createServer(
            $request->user(),
            $request->validated('name'),
        );

        return redirect()
            ->route('servers.show', $result['server'])
            ->with('success', 'Server created. Run the installation command on the target machine.')
            ->with('install_command', $result['install_command'])
            ->with('installation_token', $result['plain_token']);
    }

    public function show(Request $request, Server $server): Response
    {
        $this->authorize('view', $server);

        $server->load(['websites', 'services', 'latestMetric', 'jobs' => fn ($q) => $q->latest()->limit(20)]);

        $flashInstall = $request->session()->get('install_command');
        $flashToken = $request->session()->get('installation_token');

        return Inertia::render('Servers/Show', [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'hostname' => $server->hostname,
                'ip_address' => $server->ip_address,
                'status' => $server->status->value,
                'os_name' => $server->os_name,
                'os_version' => $server->os_version,
                'cpu_cores' => $server->cpu_cores,
                'memory_total' => $server->memory_total,
                'disk_total' => $server->disk_total,
                'agent_version' => $server->agent_version,
                'last_seen_at' => $server->last_seen_at?->toIso8601String(),
                'registered_at' => $server->registered_at?->toIso8601String(),
                'discovery' => $server->discovery,
                'metrics' => $server->latestMetric ? [
                    'cpu' => $server->latestMetric->cpu_percent,
                    'ram' => $server->latestMetric->memory_percent,
                    'disk' => $server->latestMetric->disk_percent,
                    'load_1' => $server->latestMetric->load_1,
                    'uptime_seconds' => $server->latestMetric->uptime_seconds,
                ] : null,
            ],
            'websites' => $server->websites->map(fn ($website) => [
                'id' => $website->id,
                'primary_domain' => $website->primary_domain,
                'status' => $website->status->value,
                'webserver' => $website->webserver,
                'php_version' => $website->php_version,
                'ssl_enabled' => $website->ssl_enabled,
                'root_path' => $website->root_path,
                'framework' => $website->framework,
                'last_synced_at' => $website->last_synced_at?->toIso8601String(),
            ]),
            'services' => $server->services->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'display_name' => $service->display_name,
                'status' => $service->status,
                'enabled' => $service->enabled,
                'version' => $service->version,
            ]),
            'jobs' => $server->jobs->map(fn ($job) => [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'type' => $job->type->value,
                'status' => $job->status->value,
                'error_message' => $job->error_message,
                'created_at' => $job->created_at?->toIso8601String(),
                'completed_at' => $job->completed_at?->toIso8601String(),
            ]),
            'install_command' => $flashInstall,
            'installation_token' => $flashToken,
        ]);
    }

    public function destroy(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('delete', $server);

        $this->auditLogger->log(
            organization: $server->organization,
            action: 'server.deleted',
            user: $request->user(),
            server: $server,
            payload: ['name' => $server->name],
            request: $request,
        );

        $server->delete();

        return redirect()->route('servers.index')->with('success', 'Server deleted.');
    }

    public function discover(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);

        $this->jobService->dispatch($server, AgentCommand::DiscoverServer, user: $request->user(), priority: 80);
        $this->jobService->dispatch($server, AgentCommand::DiscoverWebsites, user: $request->user(), priority: 75);

        $this->auditLogger->log(
            organization: $server->organization,
            action: 'server.discovery_requested',
            user: $request->user(),
            server: $server,
            request: $request,
        );

        return back()->with('success', 'Discovery jobs queued.');
    }

    public function regenerateToken(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);

        $issued = \App\Models\AgentInstallation::issue($server, $request->user());
        $baseUrl = rtrim((string) config('app.url'), '/');
        $installCommand = sprintf('curl -fsSL %s/install/%s | sudo bash', $baseUrl, $issued['plain_token']);

        $this->auditLogger->log(
            organization: $server->organization,
            action: 'server.installation_token_regenerated',
            user: $request->user(),
            server: $server,
            request: $request,
        );

        return back()
            ->with('success', 'New installation token generated.')
            ->with('install_command', $installCommand)
            ->with('installation_token', $issued['plain_token']);
    }
}
