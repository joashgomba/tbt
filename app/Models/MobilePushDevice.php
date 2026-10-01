<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobilePushDevice extends Model
{
    protected $fillable = [
        'user_id', 'fcm_token', 'platform', 'device_name',
        'notify_case_updates', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'notify_case_updates' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
