<?php

namespace App\Services;

use App\Enums\WebsiteStatus;
use App\Models\Website;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class WebsiteControlService
{
    public function start(Website $website): void
    {
        $this->toggle($website, enable: true);
        $website->forceFill([
            'status' => WebsiteStatus::Active,
            'last_synced_at' => now(),
        ])->save();
    }

    public function stop(Website $website): void
    {
        $this->toggle($website, enable: false);
        $website->forceFill([
            'status' => WebsiteStatus::Disabled,
            'last_synced_at' => now(),
        ])->save();
    }

    public function restart(Website $website): void
    {
        $this->toggle($website, enable: false);
        $this->toggle($website, enable: true);
        $website->forceFill([
            'status' => WebsiteStatus::Active,
            'last_synced_at' => now(),
        ])->save();
    }

    public function enable(Website $website): void
    {
        $this->start($website);
    }

    public function disable(Website $website): void
    {
        $this->stop($website);
    }

    private function toggle(Website $website, bool $enable): void
    {
        $webserver = strtolower((string) ($website->webserver ?: 'nginx'));
        $configPath = (string) ($website->config_path ?: '');

        if ($configPath === '') {
            throw new RuntimeException('Website config_path is missing.');
        }

        $base = basename($configPath);

        if (in_array($webserver, ['nginx', ''], true)) {
            $this->toggleNginx($base, $configPath, $enable);

            return;
        }

        if (in_array($webserver, ['apache', 'apache2', 'httpd'], true)) {
            $this->toggleApache($base, $enable);

            return;
        }

        throw new RuntimeException("Unsupported webserver [{$webserver}].");
    }

    private function toggleNginx(string $base, string $configPath, bool $enable): void
    {
        $enabled = '/etc/nginx/sites-enabled/'.$base;
        $available = '/etc/nginx/sites-available/'.$base;

        if ($enable) {
            if (! $this->exists($enabled)) {
                $src = $available;
                if ($this->exists($configPath) && $configPath !== $enabled) {
                    $src = $configPath;
                }
                if (! $this->exists($src)) {
                    throw new RuntimeException('Site config not found in sites-available.');
                }
                $this->run(['ln', '-sfn', $src, $enabled]);
            }
        } else {
            if ($this->isRegularFile($enabled) && ! $this->exists($available)) {
                $this->run(['mkdir', '-p', '/etc/nginx/sites-available']);
                $this->run(['mv', '-f', $enabled, $available]);
            } else {
                $this->run(['rm', '-f', $enabled]);
            }
        }

        $test = $this->run(['nginx', '-t']);
        if (! $test['ok']) {
            throw new RuntimeException('nginx config invalid: '.$test['output']);
        }

        $reload = $this->run(['nginx', '-s', 'reload']);
        if (! $reload['ok']) {
            throw new RuntimeException('nginx reload failed: '.$reload['output']);
        }
    }

    private function toggleApache(string $base, bool $enable): void
    {
        $cmd = $enable ? 'a2ensite' : 'a2dissite';
        $toggle = $this->run([$cmd, $base]);
        if (! $toggle['ok']) {
            throw new RuntimeException("{$cmd} failed: ".$toggle['output']);
        }

        $reload = $this->run(['systemctl', 'reload', 'apache2', '--no-block']);
        if (! $reload['ok']) {
            throw new RuntimeException('apache reload failed: '.$reload['output']);
        }
    }

    private function exists(string $path): bool
    {
        return is_link($path) || is_file($path);
    }

    private function isRegularFile(string $path): bool
    {
        return is_file($path) && ! is_link($path);
    }

    /**
     * @param  list<string>  $command
     * @return array{ok: bool, output: string}
     */
    private function run(array $command): array
    {
        $bin = $command[0];
        $resolved = $this->resolveBinary($bin);
        $command[0] = $resolved;

        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            // already root
        } else {
            array_unshift($command, 'sudo', '-n');
        }

        $result = Process::timeout(30)->run($command);
        $output = trim($result->errorOutput().' '.$result->output());

        return [
            'ok' => $result->successful(),
            'output' => $output,
        ];
    }

    private function resolveBinary(string $bin): string
    {
        $map = [
            'nginx' => ['/usr/sbin/nginx', '/usr/bin/nginx'],
            'systemctl' => ['/bin/systemctl', '/usr/bin/systemctl'],
            'ln' => ['/bin/ln', '/usr/bin/ln'],
            'rm' => ['/bin/rm', '/usr/bin/rm'],
            'mv' => ['/bin/mv', '/usr/bin/mv'],
            'mkdir' => ['/bin/mkdir', '/usr/bin/mkdir'],
            'a2ensite' => ['/usr/sbin/a2ensite'],
            'a2dissite' => ['/usr/sbin/a2dissite'],
        ];

        foreach ($map[$bin] ?? [] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return $bin;
    }
}
