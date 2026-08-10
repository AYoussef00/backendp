<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSnapshot extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'server_id',
        'name',
        'display_name',
        'status',
        'enabled',
        'version',
        'meta',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'meta' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
