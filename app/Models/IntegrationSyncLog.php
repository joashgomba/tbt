<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSyncLog extends Model
{
    protected $fillable = [
        'integration_system_id', 'integration_feed_id', 'integration_webhook_id',
        'direction', 'resource_type', 'status', 'triggered_by',
        'records_processed', 'records_failed', 'request_summary',
        'response_summary', 'error_message', 'started_at', 'completed_at', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(IntegrationSystem::class, 'integration_system_id');
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(IntegrationFeed::class, 'integration_feed_id');
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(IntegrationWebhook::class, 'integration_webhook_id');
    }
}
