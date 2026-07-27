<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaConfiguration extends Model
{
    protected $fillable = [
        'stage',
        'ntb_category_id',
        'target_hours',
        'alert_threshold_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NtbCategory::class, 'ntb_category_id');
    }
}
