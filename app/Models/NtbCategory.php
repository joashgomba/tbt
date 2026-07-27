<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NtbCategory extends Model
{
    protected $fillable = [
        'code',
        'name_en',
        'name_fr',
        'name_ar',
        'description_en',
        'description_fr',
        'icon',
        'sort_order',
        'parent_id',
        'default_sla_days',
        'responsible_institution_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function responsibleInstitution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'responsible_institution_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class, 'institution_ntb_category');
    }

    public function slaConfigurations(): HasMany
    {
        return $this->hasMany(SlaConfiguration::class, 'ntb_category_id');
    }

    public function displayName(string $locale = 'en'): string
    {
        return $locale === 'fr' ? ($this->name_fr ?: $this->name_en) : ($this->name_en ?: $this->name_fr);
    }
}
