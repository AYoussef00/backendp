<?php

namespace App\Models;

use App\Enums\WebsiteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Website extends Model
{
    /** @use HasFactory<\Database\Factories\WebsiteFactory> */
    use HasFactory;

    protected $fillable = [
        'server_id',
        'name',
        'primary_domain',
        'domains',
        'root_path',
        'webserver',
        'config_path',
        'php_version',
        'ssl_enabled',
        'status',
        'framework',
        'framework_version',
        'meta',
        'detected_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'meta' => 'array',
            'ssl_enabled' => 'boolean',
            'status' => WebsiteStatus::class,
            'detected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ServerJob::class);
    }
}
