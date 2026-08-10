<?php

namespace App\Console\Commands;

use App\Enums\ServerJobStatus;
use App\Models\Server;
use App\Models\ServerJob;
use Illuminate\Console\Command;

class ZyroxDiagnoseAgentCommand extends Command
{
    protected $signature = 'zyrox:diagnose-agent {server? : Server id or name}';

    protected $description = 'Show agent/job health for a managed server';

    public function handle(): int
    {
        $query = Server::query()->withCount('websites');
        $needle = $this->argument('server');

        if (filled($needle)) {
            $query->where(function ($q) use ($needle) {
                if (ctype_digit((string) $needle)) {
                    $q->orWhere('id', (int) $needle);
                }
                $q->orWhere('name', $needle)->orWhere('hostname', $needle);
            });
        }

        $servers = $query->orderBy('id')->get();
        if ($servers->isEmpty()) {
            $this->error('No matching servers.');

            return self::FAILURE;
        }

        foreach ($servers as $server) {
            $this->newLine();
            $this->info("Server #{$server->id} {$server->name}");
            $this->line('  hostname: '.($server->hostname ?: '—'));
            $this->line('  status: '.$server->status->value);
            $this->line('  agent_id: '.($server->agent_id ?: '—'));
            $this->line('  last_seen: '.($server->last_seen_at?->toDateTimeString() ?: 'never'));
            $this->line('  websites: '.$server->websites_count);

            $open = ServerJob::query()
                ->where('server_id', $server->id)
                ->whereIn('status', [
                    ServerJobStatus::Pending->value,
                    ServerJobStatus::Running->value,
                ])
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'type', 'status', 'priority', 'created_at', 'started_at']);

            if ($open->isEmpty()) {
                $this->line('  open jobs: none');
            } else {
                $this->line('  open jobs:');
                foreach ($open as $job) {
                    $this->line(sprintf(
                        '    #%d %s %s p=%d created=%s started=%s',
                        $job->id,
                        $job->type->value,
                        $job->status->value,
                        $job->priority,
                        $job->created_at,
                        $job->started_at ?: '—',
                    ));
                }
            }

            $recent = ServerJob::query()
                ->where('server_id', $server->id)
                ->latest('id')
                ->limit(5)
                ->get(['id', 'type', 'status', 'error_code', 'error_message', 'created_at']);

            $this->line('  recent jobs:');
            foreach ($recent as $job) {
                $this->line(sprintf(
                    '    #%d %s %s %s %s',
                    $job->id,
                    $job->type->value,
                    $job->status->value,
                    $job->error_code ?: '-',
                    str((string) $job->error_message)->limit(80),
                ));
            }
        }

        $this->newLine();
        $this->comment('Ensure ZYROX_LOCAL_SERVER_IDS includes only the panel host server id.');
        $this->comment('On remote hosts: systemctl status syshealthd && journalctl -u syshealthd -n 40 --no-pager');

        return self::SUCCESS;
    }
}
