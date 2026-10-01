<?php

namespace App\Services\Ntb\Uam;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserAccessScopeService
{
    /** @var list<string> */
    private const PLATFORM_ADMIN_ROLES = ['system_administrator', 'mtt_admin'];

    /** @var list<string> */
    public const NATIONAL_FOCAL_ASSIGNABLE_ROLES = [
        'institutional_officer',
        'economic_operator',
        'nmc_member',
        'analyst',
        'auditor',
    ];

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

        if ($actor->can('admin.roles') || $actor->hasRole(self::PLATFORM_ADMIN_ROLES)) {
            return $query;
        }

        if ($actor->hasRole('national_focal_point')) {
            return $this->applyNationalFocalUserScope($query);
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

        if ($actor->can('admin.roles') || $actor->hasRole(self::PLATFORM_ADMIN_ROLES)) {
            return true;
        }

        if ($actor->hasRole('national_focal_point')) {
            return $this->isManageableByNationalFocal($target);
        }

        if ($actor->hasRole('institutional_administrator') && $actor->institution_id) {
            return $target->institution_id === $actor->institution_id;
        }

        return false;
    }

    public function canAccessInstitution(Request $request, int $institutionId): bool
    {
        $actor = $request->user();
        if (! $actor) {
            return false;
        }

        if ($actor->can('admin.roles') || $actor->hasRole(self::PLATFORM_ADMIN_ROLES)) {
            return true;
        }

        if ($actor->hasRole('national_focal_point')) {
            return true;
        }

        return $actor->hasRole('institutional_administrator')
            && $actor->institution_id
            && (int) $actor->institution_id === $institutionId;
    }

    public function portalReportersQuery(): Builder
    {
        return User::query()
            ->where(function ($q) {
                $q->where('account_category', 'external')
                    ->orWhereHas('roles', fn ($r) => $r->where('name', 'economic_operator'));
            })
            ->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', [
                'system_administrator',
                'mtt_admin',
                'institutional_administrator',
                'national_focal_point',
                'institutional_officer',
            ]));
    }

    private function isExternalPortalReporter(User $target): bool
    {
        return $target->isPortalReporterOnly()
            || ($target->account_category === 'external' && $target->hasRole('economic_operator'));
    }

    private function applyNationalFocalUserScope(Builder $query): Builder
    {
        return $query->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', self::PLATFORM_ADMIN_ROLES));
    }

    private function isManageableByNationalFocal(User $target): bool
    {
        if ($target->hasRole(self::PLATFORM_ADMIN_ROLES)) {
            return false;
        }

        if ($target->hasRole('national_focal_point')) {
            return false;
        }

        if ($this->isExternalPortalReporter($target)) {
            return true;
        }

        if ($target->institution_id) {
            return true;
        }

        if (in_array($target->account_category, ['institutional', 'national', 'regional', 'service', 'technical'], true)) {
            return true;
        }

        return $target->hasRole([
            'institutional_officer',
            'institutional_administrator',
            'nmc_member',
            'analyst',
            'auditor',
        ]);
    }

    public function canAssignRole(Request $request, string $roleName): bool
    {
        $privileged = ['system_administrator', 'mtt_admin', 'institutional_administrator', 'national_focal_point'];
        $actor = $request->user();

        if (! $actor) {
            return false;
        }

        if (in_array($roleName, $privileged, true) && ! $actor->hasRole(self::PLATFORM_ADMIN_ROLES)) {
            return false;
        }

        if ($actor->hasRole('national_focal_point') && ! $actor->hasRole(self::PLATFORM_ADMIN_ROLES)) {
            return in_array($roleName, self::NATIONAL_FOCAL_ASSIGNABLE_ROLES, true);
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
