<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->current_organization_id === null) {
            $organization = $user->organizations()->first();

            if ($organization) {
                $user->forceFill(['current_organization_id' => $organization->id])->save();
            }
        }

        if ($user->current_organization_id) {
            setPermissionsTeamId($user->current_organization_id);
        }

        return $next($request);
    }
}
