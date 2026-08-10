<?php

namespace Tests\Feature;

use App\Enums\AgentCommand;
use App\Enums\ServerJobStatus;
use App\Models\Server;
use App\Models\ServerJob;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_discovery_and_website_sync(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $org = app(OrganizationService::class)->createForUser($user, 'Sync Org');

        $server = Server::factory()->online()->create([
            'organization_id' => $org->id,
        ]);
        $server->setAgentSecret('top-secret');
        $server->save();

        $this->withHeaders([
            'X-Agent-Id' => $server->agent_id,
            'X-Agent-Secret' => 'top-secret',
        ])->postJson('/api/agent/v1/discovery', [
            'hostname' => 'web-1',
            'os' => ['name' => 'Ubuntu', 'version' => '24.04'],
            'cpu' => ['cores' => 4],
            'memory' => ['total' => 8589934592],
            'services' => [
                ['name' => 'nginx', 'status' => 'running', 'enabled' => true, 'version' => '1.24'],
            ],
        ])->assertOk();

        $this->withHeaders([
            'X-Agent-Id' => $server->agent_id,
            'X-Agent-Secret' => 'top-secret',
        ])->postJson('/api/agent/v1/websites', [
            'websites' => [
                [
                    'domain' => 'example.com',
                    'aliases' => ['www.example.com'],
                    'root_path' => '/var/www/example.com/public',
                    'config_path' => '/etc/nginx/sites-enabled/example.com',
                    'webserver' => 'nginx',
                    'ssl' => true,
                    'status' => 'active',
                    'framework' => 'laravel',
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('websites', [
            'server_id' => $server->id,
            'primary_domain' => 'example.com',
            'webserver' => 'nginx',
        ]);
    }

    public function test_job_claim_and_result_flow(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $org = app(OrganizationService::class)->createForUser($user, 'Jobs Org');
        $server = Server::factory()->online()->create(['organization_id' => $org->id]);
        $server->setAgentSecret('secret');
        $server->save();

        $job = ServerJob::query()->create([
            'organization_id' => $org->id,
            'server_id' => $server->id,
            'type' => AgentCommand::DiscoverServer,
            'status' => ServerJobStatus::Pending,
            'payload' => [],
            'expires_at' => now()->addMinutes(10),
        ]);

        $claim = $this->withHeaders([
            'X-Agent-Id' => $server->agent_id,
            'X-Agent-Secret' => 'secret',
        ])->getJson('/api/agent/v1/jobs')
            ->assertOk()
            ->json('data.job');

        $this->assertSame($job->uuid, $claim['uuid']);

        $this->withHeaders([
            'X-Agent-Id' => $server->agent_id,
            'X-Agent-Secret' => 'secret',
        ])->postJson('/api/agent/v1/jobs/'.$job->id.'/result', [
            'success' => true,
            'result' => ['ok' => true],
        ])->assertOk();

        $this->assertSame(ServerJobStatus::Success, $job->fresh()->status);
    }

    public function test_registration_creates_hashed_secret_not_plaintext(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $org = app(OrganizationService::class)->createForUser($user, 'Hash Org');
        $server = Server::factory()->create(['organization_id' => $org->id]);
        $issued = \App\Models\AgentInstallation::issue($server, $user);

        $response = $this->postJson('/api/agent/v1/register', [
            'installation_token' => $issued['plain_token'],
            'hostname' => 'hashed-box',
        ])->assertCreated();

        $secret = $response->json('data.agent_secret');
        $server->refresh();

        $this->assertNotSame($secret, $server->agent_secret_hash);
        $this->assertTrue(Hash::check($secret, $server->agent_secret_hash));
        $this->assertNotNull($issued['installation']->fresh()->used_at);
    }
}
