<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    protected $fillable = [
        'parent_id',
        'institution_code',
        'institution_name',
        'name_en',
        'name_fr',
        'institution_type',
        'country_code',
        'contact_email',
        'contact_phone',
        'address',
        'mandate',
        'escalation_contacts',
        'notification_channels',
        'operating_hours',
        'integration_code',
        'status',
        'suspension_reason',
        'suspended_at',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'escalation_contacts' => 'array',
            'notification_channels' => 'array',
            'operating_hours' => 'array',
            'suspended_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'assigned_institution_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(InstitutionUnit::class);
    }

    public function corridors(): BelongsToMany
    {
        return $this->belongsToMany(Corridor::class, 'institution_corridor');
    }

    public function ntbCategories(): BelongsToMany
    {
        return $this->belongsToMany(NtbCategory::class, 'institution_ntb_category');
    }

    public function displayName(string $locale = 'en'): string
    {
        if ($locale === 'fr' && $this->name_fr) {
            return $this->name_fr;
        }

        return $this->name_en ?: $this->institution_name;
    }
}
