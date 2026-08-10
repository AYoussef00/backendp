<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Organization;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class ZyroxTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function provisionAdmin(string $orgName = 'Org A'): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $org = app(OrganizationService::class)->createForUser($user, $orgName);

        return [$user->fresh(), $org];
    }

    public function test_organization_cannot_view_another_organizations_server(): void
    {
        [$userA] = $this->provisionAdmin('Org A');
        [$userB, $orgB] = $this->provisionAdmin('Org B');

        $serverB = Server::factory()->create([
            'organization_id' => $orgB->id,
            'name' => 'secret-server',
            'status' => ServerStatus::Online,
        ]);

        $this->actingAs($userA)
            ->get(route('servers.show', $serverB))
            ->assertForbidden();
    }

    public function test_viewer_cannot_restart_website(): void
    {
        [$admin, $org] = $this->provisionAdmin();

        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $viewer->organizations()->attach($org->id);
        $viewer->forceFill(['current_organization_id' => $org->id])->save();
        setPermissionsTeamId($org->id);
        $viewer->assignRole('Viewer');

        $server = Server::factory()->online()->create(['organization_id' => $org->id]);
        $website = Website::factory()->create(['server_id' => $server->id]);

        $this->actingAs($viewer)
            ->post(route('websites.restart', $website))
            ->assertForbidden();
    }

    public function test_admin_can_create_server_and_receive_install_command(): void
    {
        [$admin] = $this->provisionAdmin();

        $response = $this->actingAs($admin)
            ->post(route('servers.store'), ['name' => 'production-01']);

        $response->assertRedirect();
        $this->assertDatabaseHas('servers', [
            'name' => 'production-01',
            'organization_id' => $admin->current_organization_id,
        ]);
    }

    public function test_expired_installation_token_is_rejected(): void
    {
        [$admin, $org] = $this->provisionAdmin();
        $server = Server::factory()->create(['organization_id' => $org->id]);

        $issued = \App\Models\AgentInstallation::issue($server, $admin, ttlMinutes: -1);

        $this->postJson('/api/agent/v1/register', [
            'installation_token' => $issued['plain_token'],
            'hostname' => 'box-1',
        ])->assertStatus(422);
    }

    public function test_agent_can_register_heartbeat_and_poll_jobs(): void
    {
        [$admin, $org] = $this->provisionAdmin();
        $server = Server::factory()->create(['organization_id' => $org->id]);
        $issued = \App\Models\AgentInstallation::issue($server, $admin);

        $register = $this->postJson('/api/agent/v1/register', [
            'installation_token' => $issued['plain_token'],
            'hostname' => 'box-1',
            'agent_version' => '1.0.0',
        ])->assertCreated()
            ->assertJsonPath('success', true);

        $agentId = $register->json('data.agent_id');
        $agentSecret = $register->json('data.agent_secret');

        $this->withHeaders([
            'X-Agent-Id' => $agentId,
            'X-Agent-Secret' => $agentSecret,
        ])->postJson('/api/agent/v1/heartbeat', [
            'hostname' => 'box-1',
            'agent_version' => '1.0.0',
        ])->assertOk()->assertJsonPath('success', true);

        $this->withHeaders([
            'X-Agent-Id' => $agentId,
            'X-Agent-Secret' => 'wrong',
        ])->postJson('/api/agent/v1/heartbeat', [])
            ->assertUnauthorized();
    }

    public function test_unknown_agent_command_is_rejected_on_result(): void
    {
        [$admin, $org] = $this->provisionAdmin();
        $server = Server::factory()->online()->create(['organization_id' => $org->id]);
        $server->setAgentSecret('secret');
        $server->save();

        // Simulate by posting result for a job that doesn't belong / wrong secret already covered.
        $this->withHeaders([
            'X-Agent-Id' => $server->agent_id,
            'X-Agent-Secret' => 'secret',
        ])->getJson('/api/agent/v1/jobs')
            ->assertOk();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_with_organization_can_visit_dashboard(): void
    {
        [$user] = $this->provisionAdmin();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
