<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerMetric extends Model
{
    /** @use HasFactory<\Database\Factories\ServerMetricFactory> */
    use HasFactory;

    protected $fillable = [
        'server_id',
        'cpu_percent',
        'memory_percent',
        'memory_used',
        'disk_percent',
        'disk_used',
        'load_1',
        'load_5',
        'load_15',
        'uptime_seconds',
        'network',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'cpu_percent' => 'float',
            'memory_percent' => 'float',
            'disk_percent' => 'float',
            'load_1' => 'float',
            'load_5' => 'float',
            'load_15' => 'float',
            'memory_used' => 'integer',
            'disk_used' => 'integer',
            'uptime_seconds' => 'integer',
            'network' => 'array',
            'collected_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
