<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowTransition extends Model
{
    protected $fillable = [
        'from_status',
        'to_status',
        'label_en',
        'label_fr',
        'required_permission',
        'requires_comment',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_comment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
