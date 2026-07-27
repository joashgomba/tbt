<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessReviewDecision extends Model
{
    protected $fillable = [
        'access_review_id',
        'subject_user_id',
        'decision',
        'new_role_id',
        'notes',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(AccessReview::class, 'access_review_id');
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
