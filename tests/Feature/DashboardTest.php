<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        app(OrganizationService::class)->createForUser($user, 'Dashboard Org');

        $this->actingAs($user->fresh());

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }
}
