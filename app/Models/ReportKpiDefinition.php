<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportKpiDefinition extends Model
{
    protected $fillable = [
        'code',
        'title_en',
        'title_fr',
        'description_en',
        'description_fr',
        'formula',
        'unit',
        'target_value',
        'warning_threshold',
        'frequency',
        'version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'warning_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function localizedTitle(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $locale === 'fr' ? ($this->title_fr ?: $this->title_en) : $this->title_en;
    }
}
