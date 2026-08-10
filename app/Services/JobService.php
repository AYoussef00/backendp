<?php

namespace App\Services;

use App\Enums\AgentCommand;
use App\Enums\ServerJobStatus;
use App\Models\Server;
use App\Models\ServerJob;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class JobService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(
        Server $server,
        AgentCommand $type,
        array $payload = [],
        ?Website $website = null,
        ?User $user = null,
        ?string $idempotencyKey = null,
        int $priority = 50,
        ?int $expiresInMinutes = 15,
    ): ServerJob {
        if ($website !== null && $website->server_id !== $server->id) {
            throw ValidationException::withMessages([
                'website' => 'Website does not belong to this server.',
            ]);
        }

        if ($idempotencyKey !== null) {
            $existing = ServerJob::query()
                ->where('server_id', $server->id)
                ->where('idempotency_key', $idempotencyKey)
                ->whereIn('status', [
                    ServerJobStatus::Pending->value,
                    ServerJobStatus::Running->value,
                    ServerJobStatus::Success->value,
                ])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        if ($website !== null) {
            $this->assertNoConflictingWebsiteJob($website);
        }

        return ServerJob::query()->create([
            'organization_id' => $server->organization_id,
            'server_id' => $server->id,
            'website_id' => $website?->id,
            'created_by' => $user?->id,
            'type' => $type,
            'payload' => $payload,
            'status' => ServerJobStatus::Pending,
            'priority' => $priority,
            'idempotency_key' => $idempotencyKey,
            'expires_at' => now()->addMinutes($expiresInMinutes ?? $type->timeoutSeconds()),
        ]);
    }

    public function claimNext(Server $server): ?ServerJob
    {
        return Cache::lock("server:{$server->id}:job-claim", 10)->block(5, function () use ($server) {
            $job = ServerJob::query()
                ->where('server_id', $server->id)
                ->where('status', ServerJobStatus::Pending)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->orderByDesc('priority')
                ->orderBy('id')
                ->first();

            if ($job === null) {
                return null;
            }

            if ($job->isExpired()) {
                $job->update(['status' => ServerJobStatus::Expired, 'completed_at' => now()]);

                return null;
            }

            $job->markRunning();

            return $job->fresh();
        });
    }

    private function assertNoConflictingWebsiteJob(Website $website): void
    {
        $lockKey = "server:{$website->server_id}:website:{$website->id}";

        $conflict = ServerJob::query()
            ->where('website_id', $website->id)
            ->whereIn('status', [ServerJobStatus::Pending->value, ServerJobStatus::Running->value])
            ->whereIn('type', [
                AgentCommand::WebsiteStart->value,
                AgentCommand::WebsiteStop->value,
                AgentCommand::WebsiteRestart->value,
                AgentCommand::WebsiteEnable->value,
                AgentCommand::WebsiteDisable->value,
            ])
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'website' => 'Another management job is already running for this website.',
            ]);
        }

        // Soft advisory lock marker for concurrent requests.
        Cache::lock($lockKey, 30)->get();
    }
}
