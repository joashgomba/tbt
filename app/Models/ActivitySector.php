<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivitySector extends Model
{
    protected $fillable = [
        'code',
        'name_en',
        'name_fr',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function displayName(string $locale = 'en'): string
    {
        return $locale === 'fr' ? ($this->name_fr ?: $this->name_en) : ($this->name_en ?: $this->name_fr);
    }
}
