<?php

namespace Database\Factories;

use App\Enums\ServerStatus;
use App\Models\Organization;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->domainWord().'-server',
            'hostname' => fake()->domainName(),
            'status' => ServerStatus::Pending,
            'agent_id' => null,
            'agent_secret_hash' => null,
        ];
    }

    public function online(): static
    {
        return $this->state(fn () => [
            'status' => ServerStatus::Online,
            'agent_id' => (string) Str::uuid(),
            'agent_secret_hash' => bcrypt('secret'),
            'last_seen_at' => now(),
            'registered_at' => now(),
        ]);
    }
}
