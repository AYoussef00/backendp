<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;
use App\Support\Permissions;

class WebsitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::WEBSITES_VIEW);
    }

    public function view(User $user, Website $website): bool
    {
        return $this->sameOrganization($user, $website)
            && $user->can(Permissions::WEBSITES_VIEW);
    }

    public function manage(User $user, Website $website): bool
    {
        return $this->sameOrganization($user, $website)
            && $user->can(Permissions::WEBSITES_MANAGE);
    }

    private function sameOrganization(User $user, Website $website): bool
    {
        $website->loadMissing('server');

        return (int) $user->current_organization_id === (int) $website->server->organization_id;
    }
}
