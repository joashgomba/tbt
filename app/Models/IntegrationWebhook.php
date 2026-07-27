<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationWebhook extends Model
{
    protected $fillable = [
        'integration_system_id', 'name', 'target_url', 'signing_secret_hash',
        'event_types', 'max_retries', 'timeout_seconds', 'is_active',
        'last_triggered_at', 'last_status_code', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'event_types' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(IntegrationSystem::class, 'integration_system_id');
    }
}
