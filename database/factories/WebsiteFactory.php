<?php

namespace Database\Factories;

use App\Enums\WebsiteStatus;
use App\Models\Server;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Website>
 */
class WebsiteFactory extends Factory
{
    protected $model = Website::class;

    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'server_id' => Server::factory(),
            'name' => $domain,
            'primary_domain' => $domain,
            'domains' => ['www.'.$domain],
            'root_path' => '/var/www/'.$domain.'/public',
            'webserver' => 'nginx',
            'config_path' => '/etc/nginx/sites-enabled/'.$domain,
            'php_version' => '8.3',
            'ssl_enabled' => true,
            'status' => WebsiteStatus::Active,
            'detected_at' => now(),
            'last_synced_at' => now(),
        ];
    }
}
