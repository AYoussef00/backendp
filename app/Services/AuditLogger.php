<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function log(
        Organization $organization,
        string $action,
        ?User $user = null,
        ?Server $server = null,
        ?Website $website = null,
        ?array $payload = null,
        string $result = 'success',
        ?Request $request = null,
    ): AuditLog {
        $safePayload = $this->sanitize($payload);

        return AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user?->id,
            'server_id' => $server?->id,
            'website_id' => $website?->id,
            'action' => $action,
            'payload' => $safePayload,
            'result' => $result,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $sensitive = ['password', 'secret', 'token', 'agent_secret', 'content', 'private_key'];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $payload[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $payload[$key] = $this->sanitize($value);
            }
        }

        return $payload;
    }
}
