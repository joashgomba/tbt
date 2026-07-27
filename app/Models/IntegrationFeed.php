<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationFeed extends Model
{
    protected $fillable = [
        'integration_system_id', 'feed_code', 'name', 'direction', 'resource_type',
        'sync_mode', 'endpoint_url', 'http_method', 'schedule_cron', 'schedule_enabled',
        'field_mapping', 'headers', 'is_active', 'last_run_at', 'last_success_at',
        'last_error', 'last_records_processed',
    ];

    protected function casts(): array
    {
        return [
            'field_mapping' => 'array',
            'headers' => 'array',
            'schedule_enabled' => 'boolean',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(IntegrationSystem::class, 'integration_system_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(IntegrationSyncLog::class);
    }
}
