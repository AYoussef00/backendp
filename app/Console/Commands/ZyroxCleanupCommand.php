<?php

namespace App\Console\Commands;

use App\Enums\ServerJobStatus;
use App\Models\AgentInstallation;
use App\Models\ServerJob;
use App\Models\ServerMetric;
use Illuminate\Console\Command;

class ZyroxCleanupCommand extends Command
{
    protected $signature = 'zyrox:cleanup
                            {--jobs : Cleanup expired/old jobs}
                            {--stuck : Cancel all pending/running jobs}
                            {--metrics : Cleanup old metrics}
                            {--tokens : Cleanup expired installation tokens}
                            {--all : Run all cleanup tasks}';

    protected $description = 'Cleanup expired agent tokens, jobs, and metrics';

    public function handle(): int
    {
        $all = (bool) $this->option('all');

        if ($all || $this->option('tokens')) {
            $tokens = AgentInstallation::query()
                ->whereNull('used_at')
                ->where('expires_at', '<', now())
                ->delete();
            $this->info("Deleted {$tokens} expired installation tokens.");
        }

        if ($this->option('stuck')) {
            $cancelled = ServerJob::query()
                ->whereIn('status', [
                    ServerJobStatus::Pending->value,
                    ServerJobStatus::Running->value,
                ])
                ->update([
                    'status' => ServerJobStatus::Cancelled,
                    'completed_at' => now(),
                    'error_code' => 'JOB_CANCELLED',
                    'error_message' => 'Cancelled manually (stuck job cleanup).',
                ]);

            $this->info("Cancelled {$cancelled} stuck pending/running jobs.");
        }

        if ($all || $this->option('jobs')) {
            $expired = ServerJob::query()
                ->where('status', ServerJobStatus::Pending)
                ->where('expires_at', '<', now())
                ->update([
                    'status' => ServerJobStatus::Expired,
                    'completed_at' => now(),
                ]);

            $retention = (int) config('zyrox.jobs_retention_days', 30);
            $deleted = ServerJob::query()
                ->where('created_at', '<', now()->subDays($retention))
                ->whereIn('status', [
                    ServerJobStatus::Success->value,
                    ServerJobStatus::Failed->value,
                    ServerJobStatus::Expired->value,
                    ServerJobStatus::Cancelled->value,
                ])
                ->delete();

            $this->info("Expired {$expired} jobs; deleted {$deleted} old jobs.");
        }

        if ($all || $this->option('metrics')) {
            $days = (int) config('zyrox.metrics_retention_days', 7);
            $deleted = ServerMetric::query()
                ->where('collected_at', '<', now()->subDays($days))
                ->delete();
            $this->info("Deleted {$deleted} old metrics.");
        }

        return self::SUCCESS;
    }
}
