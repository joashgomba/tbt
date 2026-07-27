<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NtbNotification extends Model
{
    protected $table = 'ntb_notifications';

    protected $fillable = [
        'complaint_id',
        'user_id',
        'channel',
        'recipient',
        'subject',
        'message',
        'event_type',
        'delivery_status',
        'sent_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
