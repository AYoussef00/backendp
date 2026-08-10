<?php

namespace App\Models;

use App\Enums\ServerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Server extends Model
{
    /** @use HasFactory<\Database\Factories\ServerFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'hostname',
        'ip_address',
        'status',
        'agent_id',
        'agent_secret_hash',
        'agent_public_key',
        'agent_version',
        'os_name',
        'os_version',
        'cpu_cores',
        'memory_total',
        'disk_total',
        'discovery',
        'last_seen_at',
        'registered_at',
    ];

    protected $hidden = [
        'agent_secret_hash',
        'agent_public_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServerStatus::class,
            'discovery' => 'array',
            'last_seen_at' => 'datetime',
            'registered_at' => 'datetime',
            'cpu_cores' => 'integer',
            'memory_total' => 'integer',
            'disk_total' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ServerJob::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(AgentInstallation::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ServerMetric::class);
    }

    public function latestMetric(): HasOne
    {
        return $this->hasOne(ServerMetric::class)->latestOfMany('collected_at');
    }

    public function services(): HasMany
    {
        return $this->hasMany(ServiceSnapshot::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isOnline(): bool
    {
        return $this->status === ServerStatus::Online
            && $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes(2));
    }

    public function verifyAgentSecret(string $secret): bool
    {
        return filled($this->agent_secret_hash)
            && Hash::check($secret, $this->agent_secret_hash);
    }

    public function setAgentSecret(string $secret): void
    {
        $this->agent_secret_hash = Hash::make($secret);
    }

    public static function generateAgentCredentials(): array
    {
        return [
            'agent_id' => (string) Str::uuid(),
            'agent_secret' => Str::random(64),
        ];
    }
}
