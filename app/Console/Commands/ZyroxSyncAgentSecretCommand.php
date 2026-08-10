<?php

namespace App\Console\Commands;

use App\Models\Server;
use Illuminate\Console\Command;

class ZyroxSyncAgentSecretCommand extends Command
{
    protected $signature = 'zyrox:sync-agent-secret
                            {--file=/etc/syshealth/config.env : Path to agent config.env file}';

    protected $description = 'Sync agent secret from host config.env into the panel database';

    public function handle(): int
    {
        $path = (string) $this->option('file');

        if (! is_readable($path)) {
            $this->error("Cannot read {$path}");

            return self::FAILURE;
        }

        $map = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $map[trim($key)] = trim($value);
        }

        $agentId = $map['SHD_ID'] ?? null;
        $secret = $map['SHD_SECRET'] ?? null;

        if (! filled($agentId) || ! filled($secret)) {
            $this->error('SHD_ID / SHD_SECRET missing in env file.');

            return self::FAILURE;
        }

        $server = Server::query()->where('agent_id', $agentId)->first();
        if ($server === null) {
            $this->error("No server found for agent_id={$agentId}");

            return self::FAILURE;
        }

        $server->setAgentSecret($secret);
        $server->save();

        $this->info("Synced secret for server #{$server->id} ({$server->name}) agent_id={$agentId}");

        return self::SUCCESS;
    }
}
