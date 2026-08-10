<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AgentInstallation extends Model
{
    /** @use HasFactory<\Database\Factories\AgentInstallationFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'server_id',
        'created_by',
        'token_hash',
        'token_prefix',
        'expires_at',
        'used_at',
        'used_by_ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function verify(string $plainToken): bool
    {
        return $this->isValid() && Hash::check($plainToken, $this->token_hash);
    }

    /**
     * @return array{installation: self, plain_token: string}
     */
    public static function issue(Server $server, ?User $user = null, int $ttlMinutes = 60): array
    {
        $plain = Str::random(48);

        $installation = static::query()->create([
            'organization_id' => $server->organization_id,
            'server_id' => $server->id,
            'created_by' => $user?->id,
            'token_hash' => Hash::make($plain),
            'token_prefix' => substr($plain, 0, 8),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return [
            'installation' => $installation,
            'plain_token' => $plain,
        ];
    }
}
