<?php

namespace App\Models;

use App\Enums\AgentCommand;
use App\Enums\ServerJobStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ServerJob extends Model
{
    /** @use HasFactory<\Database\Factories\ServerJobFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'organization_id',
        'server_id',
        'website_id',
        'created_by',
        'type',
        'payload',
        'status',
        'priority',
        'attempts',
        'max_attempts',
        'idempotency_key',
        'result',
        'error_code',
        'error_message',
        'expires_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AgentCommand::class,
            'status' => ServerJobStatus::class,
            'payload' => 'array',
            'result' => 'array',
            'expires_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'priority' => 'integer',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ServerJob $job): void {
            if (blank($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function markRunning(): void
    {
        $this->update([
            'status' => ServerJobStatus::Running,
            'started_at' => now(),
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markSuccess(?array $result = null): void
    {
        $this->update([
            'status' => ServerJobStatus::Success,
            'result' => $result,
            'completed_at' => now(),
            'error_code' => null,
            'error_message' => null,
        ]);
    }

    public function markFailed(string $code, string $message, ?array $result = null): void
    {
        $this->update([
            'status' => ServerJobStatus::Failed,
            'error_code' => $code,
            'error_message' => $message,
            'result' => $result,
            'completed_at' => now(),
        ]);
    }
}
