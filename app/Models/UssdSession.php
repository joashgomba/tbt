<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UssdSession extends Model
{
    protected $fillable = [
        'session_id',
        'msisdn',
        'state',
        'payload',
        'user_id',
        'complaint_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(UssdSessionEvent::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
