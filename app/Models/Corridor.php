<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Corridor extends Model
{
    protected $fillable = [
        'corridor_code',
        'corridor_name',
        'name_en',
        'name_fr',
        'description_en',
        'description_fr',
        'origin_country',
        'destination_country',
        'primary_mode',
        'distance_km',
        'transit_time_hours',
        'icon',
        'sort_order',
        'active',
        'deactivation_reason',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
            'distance_km' => 'integer',
            'transit_time_hours' => 'integer',
            'deactivated_at' => 'datetime',
        ];
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class, 'institution_corridor');
    }

    public function displayName(string $locale = 'en'): string
    {
        if ($locale === 'fr') {
            return $this->name_fr ?: $this->name_en ?: $this->corridor_name;
        }

        return $this->name_en ?: $this->name_fr ?: $this->corridor_name;
    }

    public function routeLabel(): string
    {
        return "{$this->origin_country} → {$this->destination_country}";
    }
}
