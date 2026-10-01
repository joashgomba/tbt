<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationSystem extends Model
{
    /** @var list<string> */
    protected $hidden = [
        'auth_config',
    ];

    protected $fillable = [
        'system_code', 'name', 'name_en', 'name_fr', 'system_type', 'direction',
        'country', 'institution_id', 'base_url', 'auth_method', 'auth_config',
        'contact_name', 'contact_email', 'description_en', 'description_fr',
        'metadata', 'is_active', 'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'auth_config' => 'encrypted:array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'last_sync_at' => 'datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function feeds(): HasMany
    {
        return $this->hasMany(IntegrationFeed::class);
    }

    public function apiClients(): HasMany
    {
        return $this->hasMany(IntegrationApiClient::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(IntegrationWebhook::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(IntegrationSyncLog::class);
    }
}
