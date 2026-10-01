<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UssdSessionEvent extends Model
{
    protected $fillable = [
        'ussd_session_id',
        'state',
        'user_input',
        'system_message',
        'session_closed',
    ];

    protected function casts(): array
    {
        return [
            'session_closed' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(UssdSession::class, 'ussd_session_id');
    }
}
