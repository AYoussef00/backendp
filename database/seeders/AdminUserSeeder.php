<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\OrganizationService;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@zyrox.test'],
            [
                'name' => 'ZYROX Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $organizationService = app(OrganizationService::class);

        if ($admin->organizations()->count() === 0) {
            $organizationService->createForUser($admin, 'ZYROX Demo');

            return;
        }

        $organization = $admin->organizations()->firstOrFail();
        $admin->forceFill(['current_organization_id' => $organization->id])->save();

        $organizationService->ensureRolesAndPermissions($organization);
        setPermissionsTeamId($organization->id);

        if (! $admin->hasRole('Admin')) {
            $admin->assignRole('Admin');
        }
    }
}
