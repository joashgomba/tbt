<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Escalation extends Model
{
    protected $fillable = [
        'complaint_id',
        'escalation_level',
        'escalation_type',
        'escalation_reason',
        'escalated_to_user_id',
        'escalated_to_institution_id',
        'escalation_date',
        'resolution_date',
    ];

    protected function casts(): array
    {
        return [
            'escalation_date' => 'datetime',
            'resolution_date' => 'datetime',
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
}
