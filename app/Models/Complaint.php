<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    protected $fillable = [
        'complaint_code',
        'title',
        'description',
        'ntb_category_id',
        'status_id',
        'priority',
        'country_origin',
        'country_destination',
        'corridor_id',
        'location_id',
        'created_by',
        'reporter_name',
        'reporter_email',
        'reporter_phone',
        'assigned_institution_id',
        'current_handler_id',
        'submission_channel',
        'gender',
        'resolution_note',
        'closure_summary',
        'sla_deadline',
        'closed_at',
        'archived_at',
        'notify_reporter',
    ];

    protected function casts(): array
    {
        return [
            'sla_deadline' => 'datetime',
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
            'notify_reporter' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NtbCategory::class, 'ntb_category_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ComplaintStatus::class, 'status_id');
    }

    public function corridor(): BelongsTo
    {
        return $this->belongsTo(Corridor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedInstitution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'assigned_institution_id');
    }

    public function currentHandler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_handler_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CaseHistory::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class);
    }

    public function slaTrackings(): HasMany
    {
        return $this->hasMany(SlaTracking::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NtbNotification::class);
    }
}
