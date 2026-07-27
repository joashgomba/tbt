<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplaintStatus extends Model
{
    protected $fillable = [
        'code',
        'name_en',
        'name_fr',
        'sort_order',
        'is_terminal',
    ];

    protected function casts(): array
    {
        return ['is_terminal' => 'boolean'];
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'status_id');
    }
}
