<?php

namespace App\Services\Ntb\Institutions;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class InstitutionScopeService
{
    public function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user && ($user->can('admin.institutions') || $user->hasRole(['mtt_admin', 'system_administrator']));
    }

    public function canViewInstitution(Request $request, Institution $institution): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if ($this->canManage($request)) {
            return true;
        }

        if ($user->hasRole('institutional_administrator') && $user->institution_id === $institution->id) {
            return true;
        }

        return $user->institution_id === $institution->id && $user->can('admin.users');
    }

    public function scopedQuery(Request $request): Builder
    {
        $query = Institution::query();
        $user = $request->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->canManage($request)) {
            return $query;
        }

        if ($user->institution_id) {
            return $query->where('id', $user->institution_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function canModifyInstitution(Request $request, Institution $institution): bool
    {
        if ($this->canManage($request)) {
            return true;
        }

        $user = $request->user();

        return $user
            && $user->hasRole('institutional_administrator')
            && $user->institution_id === $institution->id
            && $institution->status === 'active';
    }
}
