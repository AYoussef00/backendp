<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ZyroxRefreshAgentCommand extends Command
{
    protected $signature = 'zyrox:refresh-agent
                            {--restart : Restart syshealthd after updating the binary}';

    protected $description = 'Rewrite /usr/local/bin/syshealthd from the latest install template (keeps existing credentials)';

    public function handle(): int
    {
        $script = view('install.agent', [
            'token' => 'refresh-only',
            'panelUrl' => rtrim((string) config('app.url'), '/'),
            'version' => config('zyrox.agent_version', '1.0.0'),
            'downloadBase' => rtrim((string) config('app.url'), '/').'/agent/download',
            'serverName' => 'local',
        ])->render();

        if (! preg_match("/cat > .* <<'AGENTEOF'(.*)AGENTEOF/s", $script, $matches)) {
            $this->error('Could not extract agent script from install template.');

            return self::FAILURE;
        }

        $binary = ltrim($matches[1]);
        if (! str_starts_with($binary, '#!/')) {
            $binary = "#!/usr/bin/env bash\n".$binary;
        }

        $path = '/usr/local/bin/syshealthd';
        if (file_put_contents($path, $binary) === false) {
            $this->error("Failed to write {$path}");

            return self::FAILURE;
        }
        chmod($path, 0755);

        $this->info("Updated {$path}");

        if ($this->option('restart')) {
            exec('systemctl restart syshealthd 2>&1', $out, $code);
            foreach ($out as $line) {
                $this->line($line);
            }
            if ($code !== 0) {
                $this->error('systemctl restart failed');

                return self::FAILURE;
            }
            $this->info('syshealthd restarted');
        }

        return self::SUCCESS;
    }
}
