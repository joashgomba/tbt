<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfaMethod extends Model
{
    protected $fillable = [
        'user_id',
        'method',
        'destination',
        'secret',
        'is_primary',
        'is_verified',
        'verified_at',
    ];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
