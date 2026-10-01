<?php

namespace App\Services\Ntb;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ComplaintScopeService
{
    /** @var list<string> */
    private const NATIONAL_ROLES = [
        'system_administrator',
        'mtt_admin',
        'national_focal_point',
        'nmc_member',
        'analyst',
    ];

    public function authorizeView(User $user): void
    {
        abort_unless(
            $user->can('complaints.view_all') || $user->can('complaints.view_own'),
            403,
            'You are not permitted to view complaints.'
        );
    }

    public function hasNationalVisibility(User $user): bool
    {
        if ($user->hasRole(self::NATIONAL_ROLES)) {
            return true;
        }

        return $user->can('complaints.view_all') && ! $user->institution_id;
    }

    public function scopeQuery(User $user): Builder
    {
        $query = Complaint::query();

        if ($this->hasNationalVisibility($user)) {
            return $query;
        }

        if ($user->institution_id && $user->can('complaints.view_all')) {
            return $query->where('assigned_institution_id', $user->institution_id);
        }

        if ($user->can('complaints.view_own')) {
            return $query->where('created_by', $user->id);
        }

        if ($user->institution_id) {
            return $query->where('assigned_institution_id', $user->institution_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function assertCanMutate(User $user, Complaint $complaint): void
    {
        if ($this->hasNationalVisibility($user)) {
            return;
        }

        if ($user->institution_id) {
            if (! $complaint->assigned_institution_id) {
                abort(403, 'This case is not yet assigned to your institution.');
            }

            if ((int) $complaint->assigned_institution_id !== (int) $user->institution_id) {
                abort(403, 'This case is assigned to another institution.');
            }
        }
    }

    public function canValidateUnassigned(User $user): bool
    {
        return $this->hasNationalVisibility($user) && $user->can('complaints.validate');
    }
}
