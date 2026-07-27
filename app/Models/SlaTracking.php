<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaTracking extends Model
{
    protected $fillable = [
        'complaint_id',
        'stage',
        'sla_target_hours',
        'started_at',
        'deadline_at',
        'completed_at',
        'is_breached',
        'breach_hours',
        'alert_sent_at',
        'institution_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'completed_at' => 'datetime',
            'alert_sent_at' => 'datetime',
            'is_breached' => 'boolean',
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
