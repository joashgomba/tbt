<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSchedule extends Model
{
    protected $fillable = [
        'report_definition_id',
        'owner_id',
        'frequency',
        'parameters',
        'recipients',
        'channels',
        'output_format',
        'starts_at',
        'ends_at',
        'is_active',
        'is_suspended',
        'last_run_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'recipients' => 'array',
            'channels' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'is_active' => 'boolean',
            'is_suspended' => 'boolean',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class, 'report_definition_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
