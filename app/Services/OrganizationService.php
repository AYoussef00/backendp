<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class OrganizationService
{
    public function createForUser(User $user, string $name): Organization
    {
        return DB::transaction(function () use ($user, $name) {
            $organization = Organization::query()->create([
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            ]);

            $user->organizations()->attach($organization->id);
            $user->forceFill(['current_organization_id' => $organization->id])->save();

            $this->ensureRolesAndPermissions($organization);

            setPermissionsTeamId($organization->id);
            $user->assignRole('Admin');

            return $organization;
        });
    }

    public function ensureRolesAndPermissions(Organization $organization): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        setPermissionsTeamId($organization->id);

        foreach (Permissions::roleMap() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }

    public function bootstrapGlobalPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}
