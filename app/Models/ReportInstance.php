<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportInstance extends Model
{
    protected $fillable = [
        'report_definition_id',
        'instance_code',
        'status',
        'parameters',
        'filters',
        'generated_by',
        'generated_at',
        'completed_at',
        'file_path',
        'file_format',
        'error_message',
        'version',
        'approval_status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'filters' => 'array',
            'metadata' => 'array',
            'generated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class, 'report_definition_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function exportLogs(): HasMany
    {
        return $this->hasMany(ReportExportLog::class);
    }
}
