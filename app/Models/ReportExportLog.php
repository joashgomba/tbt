<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExportLog extends Model
{
    protected $fillable = [
        'user_id',
        'report_instance_id',
        'report_code',
        'format',
        'filters',
        'status',
        'file_path',
        'ip_address',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ReportInstance::class, 'report_instance_id');
    }
}
