<?php

namespace App\Console\Commands;

use App\Enums\ServerJobStatus;
use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\ServerJob;
use App\Models\ServerMetric;
use Illuminate\Console\Command;

class ServerHealthCommand extends Command
{
    protected $signature = 'server:health';

    protected $description = 'Mark servers offline when heartbeat is stale';

    public function handle(): int
    {
        $grace = (int) config('zyrox.heartbeat_grace_seconds', 120);

        $updated = Server::query()
            ->where('status', '!=', ServerStatus::Pending->value)
            ->where(function ($query) use ($grace) {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', now()->subSeconds($grace));
            })
            ->update(['status' => ServerStatus::Offline->value]);

        $this->info("Marked {$updated} servers offline.");

        return self::SUCCESS;
    }
}
