<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'full_name',
        'email',
        'gender',
        'phone_number',
        'password',
        'institution_id',
        'unit_id',
        'preferred_language',
        'status',
        'account_category',
        'job_title',
        'is_focal_point',
        'is_institution_manager',
        'mfa_enrolled',
        'password_must_change',
        'suspension_reason',
        'suspended_until',
        'deactivated_at',
        'phone_verified_at',
        'terms_accepted_at',
        'created_by',
        'failed_login_attempts',
        'locked_at',
        'user_uuid',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'suspended_until' => 'datetime',
            'deactivated_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'locked_at' => 'datetime',
            'is_focal_point' => 'boolean',
            'is_institution_manager' => 'boolean',
            'mfa_enrolled' => 'boolean',
            'password_must_change' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InstitutionUnit::class, 'unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    public function delegationsGiven(): HasMany
    {
        return $this->hasMany(Delegation::class, 'delegator_id');
    }

    public function delegationsReceived(): HasMany
    {
        return $this->hasMany(Delegation::class, 'delegate_id');
    }

    public function mfaMethods(): HasMany
    {
        return $this->hasMany(MfaMethod::class);
    }

    public function portalNotifications(): HasMany
    {
        return $this->hasMany(NtbNotification::class);
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (! $user->user_uuid) {
                $user->user_uuid = (string) Str::uuid();
            }
            if (! $user->preferred_language) {
                $user->preferred_language = 'fr';
            }
        });
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'created_by');
    }

    public function displayName(): string
    {
        return $this->full_name ?: $this->name;
    }
}
