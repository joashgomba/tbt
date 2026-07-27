<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportDefinition extends Model
{
    protected $fillable = [
        'code',
        'category',
        'title_en',
        'title_fr',
        'description_en',
        'description_fr',
        'default_frequency',
        'output_formats',
        'parameters',
        'required_permissions',
        'confidentiality',
        'chart_types',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'output_formats' => 'array',
            'parameters' => 'array',
            'required_permissions' => 'array',
            'chart_types' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ReportInstance::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ReportSchedule::class);
    }

    public function localizedTitle(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $locale === 'fr' ? ($this->title_fr ?: $this->title_en) : $this->title_en;
    }
}
