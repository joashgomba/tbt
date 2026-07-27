<?php

namespace App\Services\Ntb\Uam;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserAccessScopeService
{
    public function canManageUsers(Request $request): bool
    {
        $user = $request->user();

        return $user && ($user->can('admin.users') || $user->can('admin.roles'));
    }

    public function scopedUsersQuery(Request $request): Builder
    {
        $query = User::query();
        $actor = $request->user();

        if (! $actor) {
            return $query->whereRaw('1 = 0');
        }

        if ($actor->can('admin.roles') || $actor->hasRole('system_administrator') || $actor->hasRole('mtt_admin')) {
            return $query;
        }

        if ($actor->hasRole('institutional_administrator') && $actor->institution_id) {
            return $query->where('institution_id', $actor->institution_id);
        }

        return $query->where('id', $actor->id);
    }

    public function canManageUser(Request $request, User $target): bool
    {
        $actor = $request->user();
        if (! $actor || $actor->id === $target->id) {
            return false;
        }

        if ($actor->can('admin.roles') || $actor->hasRole(['system_administrator', 'mtt_admin'])) {
            return true;
        }

        if ($actor->hasRole('institutional_administrator') && $actor->institution_id) {
            return $target->institution_id === $actor->institution_id;
        }

        return false;
    }

    public function canAssignRole(Request $request, string $roleName): bool
    {
        $privileged = ['system_administrator', 'mtt_admin', 'institutional_administrator'];
        $actor = $request->user();

        if (! $actor) {
            return false;
        }

        if (in_array($roleName, $privileged, true) && ! $actor->hasRole(['system_administrator', 'mtt_admin'])) {
            return false;
        }

        return $actor->can('admin.users') || $actor->can('admin.roles');
    }

    public function isLastSystemAdmin(User $target): bool
    {
        if (! $target->hasRole('system_administrator')) {
            return false;
        }

        return User::query()
            ->where('status', 'active')
            ->where('id', '!=', $target->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'system_administrator'))
            ->count() === 0;
    }
}
