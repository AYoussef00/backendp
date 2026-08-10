<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;
use App\Support\Permissions;

class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::SERVERS_VIEW);
    }

    public function view(User $user, Server $server): bool
    {
        return $this->sameOrganization($user, $server)
            && $user->can(Permissions::SERVERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::SERVERS_CREATE);
    }

    public function update(User $user, Server $server): bool
    {
        return $this->sameOrganization($user, $server)
            && $user->can(Permissions::SERVERS_UPDATE);
    }

    public function delete(User $user, Server $server): bool
    {
        return $this->sameOrganization($user, $server)
            && $user->can(Permissions::SERVERS_DELETE);
    }

    private function sameOrganization(User $user, Server $server): bool
    {
        return (int) $user->current_organization_id === (int) $server->organization_id;
    }
}
