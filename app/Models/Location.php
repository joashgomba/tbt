<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = [
        'location_code',
        'location_name',
        'name_en',
        'name_fr',
        'description_en',
        'description_fr',
        'location_type',
        'icon',
        'sort_order',
        'country',
        'region',
        'latitude',
        'longitude',
        'border_post',
        'is_active',
        'deactivation_reason',
        'deactivated_at',
        'corridor_id',
    ];

    protected function casts(): array
    {
        return [
            'border_post' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'deactivated_at' => 'datetime',
        ];
    }

    public function corridor(): BelongsTo
    {
        return $this->belongsTo(Corridor::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function displayName(string $locale = 'en'): string
    {
        if ($locale === 'fr') {
            return $this->name_fr ?: $this->name_en ?: $this->location_name;
        }

        return $this->name_en ?: $this->name_fr ?: $this->location_name;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
