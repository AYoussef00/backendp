<?php

namespace App\Services;

use App\Enums\AgentCommand;
use App\Enums\ServerJobStatus;
use App\Models\Server;
use App\Models\ServerJob;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
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

            // Free the unique key from a previous failed/expired attempt in the same minute.
            ServerJob::query()
                ->where('server_id', $server->id)
                ->where('idempotency_key', $idempotencyKey)
                ->whereIn('status', [
                    ServerJobStatus::Failed->value,
                    ServerJobStatus::Expired->value,
                    ServerJobStatus::Cancelled->value,
                ])
                ->update(['idempotency_key' => $idempotencyKey.':retired:'.$this->nowSuffix()]);
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
            'expires_at' => now()->addMinutes($expiresInMinutes ?? 15),
        ]);
    }

    public function claimNext(Server $server): ?ServerJob
    {
        return DB::transaction(function () use ($server) {
            $this->recoverStuckJobs($server);

            $job = ServerJob::query()
                ->where('server_id', $server->id)
                ->where('status', ServerJobStatus::Pending)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->orderByDesc('priority')
                ->orderBy('id')
                ->lockForUpdate()
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

    private function recoverStuckJobs(Server $server): void
    {
        ServerJob::query()
            ->where('server_id', $server->id)
            ->where('status', ServerJobStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => ServerJobStatus::Expired,
                'completed_at' => now(),
                'error_code' => 'JOB_EXPIRED',
                'error_message' => 'Job expired before the agent claimed it.',
            ]);

        // Re-queue jobs left running if the agent died mid-flight.
        ServerJob::query()
            ->where('server_id', $server->id)
            ->where('status', ServerJobStatus::Running)
            ->where(function ($query) {
                $query->whereNull('started_at')
                    ->orWhere('started_at', '<=', now()->subSeconds(60));
            })
            ->update([
                'status' => ServerJobStatus::Pending,
                'started_at' => null,
                'error_code' => null,
                'error_message' => null,
            ]);
    }

    private function assertNoConflictingWebsiteJob(Website $website): void
    {
        // Clear stale running website jobs so the UI is not blocked forever.
        ServerJob::query()
            ->where('website_id', $website->id)
            ->where('status', ServerJobStatus::Running)
            ->where(function ($query) {
                $query->whereNull('started_at')
                    ->orWhere('started_at', '<=', now()->subSeconds(60));
            })
            ->update([
                'status' => ServerJobStatus::Failed,
                'completed_at' => now(),
                'error_code' => 'OPERATION_FAILED',
                'error_message' => 'Previous job timed out while running on the agent.',
            ]);

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
    }

    private function nowSuffix(): string
    {
        return (string) now()->format('YmdHis');
    }
}
