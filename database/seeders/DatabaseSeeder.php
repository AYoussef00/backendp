<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationService;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $orgService = app(OrganizationService::class);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@zyrox.test'],
            [
                'name' => 'ZYROX Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        if ($admin->organizations()->count() === 0) {
            $orgService->createForUser($admin, 'ZYROX Demo');
        } else {
            $organization = $admin->organizations()->first();
            $admin->forceFill(['current_organization_id' => $organization->id])->save();
            $orgService->ensureRolesAndPermissions($organization);
            setPermissionsTeamId($organization->id);
            if (! $admin->hasRole('Admin')) {
                $admin->assignRole('Admin');
            }
        }

        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
