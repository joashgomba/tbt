<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseHistory extends Model
{
    protected $fillable = [
        'complaint_id',
        'action_type',
        'from_status',
        'to_status',
        'performed_by',
        'institution_id',
        'comment',
        'attachments',
        'action_date',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'action_date' => 'datetime',
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
