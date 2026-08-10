<?php

namespace App\Console\Commands;

use App\Models\AgentInstallation;
use App\Models\Server;
use Illuminate\Console\Command;

class ZyroxInstallAgentCommand extends Command
{
    protected $signature = 'zyrox:install-agent
                            {server : Server ID}
                            {--print : Print the install script only (do not execute)}
                            {--ttl=120 : Token lifetime in minutes}';

    protected $description = 'Generate (and optionally run) the agent install script for a server';

    public function handle(): int
    {
        $server = Server::query()->find($this->argument('server'));

        if ($server === null) {
            $this->error('Server not found.');

            return self::FAILURE;
        }

        $issued = AgentInstallation::issue(
            $server,
            null,
            (int) $this->option('ttl'),
        );

        $panelUrl = rtrim((string) config('app.url'), '/');
        $script = view('install.agent', [
            'token' => $issued['plain_token'],
            'panelUrl' => $panelUrl,
            'version' => config('zyrox.agent_version', '1.0.0'),
            'downloadBase' => $panelUrl.'/agent/download',
            'serverName' => $server->name,
        ])->render();

        if ($this->option('print')) {
            $this->line($script);

            return self::SUCCESS;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'zyrox-install-');
        if ($tmp === false) {
            $this->error('Could not create temp file.');

            return self::FAILURE;
        }

        $path = $tmp.'.sh';
        rename($tmp, $path);
        file_put_contents($path, $script);
        chmod($path, 0700);

        $this->info("Installing agent for server #{$server->id} ({$server->name})...");
        $this->line('Panel URL: '.$panelUrl);
        $this->line('Token: '.$issued['plain_token']);

        passthru('sudo bash '.escapeshellarg($path), $exitCode);
        @unlink($path);

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
