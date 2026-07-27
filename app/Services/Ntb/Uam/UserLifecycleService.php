<?php

namespace App\Services\Ntb\Uam;

use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\Ntb\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserLifecycleService
{
    public function __construct(
        private readonly UserAccessScopeService $scope,
        private readonly AuditLogger $audit,
        private readonly UamNotificationService $notifications,
    ) {}

    public function create(Request $request, array $data): User
    {
        abort_unless($this->scope->canManageUsers($request), 403);

        if (! empty($data['roles'][0])) {
            abort_unless($this->scope->canAssignRole($request, $data['roles'][0]), 403);
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'full_name' => $data['full_name'] ?? $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'gender' => $data['gender'] ?? null,
            'password' => Hash::make($data['password']),
            'institution_id' => $data['institution_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'preferred_language' => $data['preferred_language'] ?? 'fr',
            'status' => $data['status'] ?? 'pending_approval',
            'account_category' => $data['account_category'] ?? 'institutional',
            'job_title' => $data['job_title'] ?? null,
            'is_focal_point' => $data['is_focal_point'] ?? false,
            'is_institution_manager' => $data['is_institution_manager'] ?? false,
            'password_must_change' => $data['password_must_change'] ?? true,
            'created_by' => $request->user()?->id,
            'terms_accepted_at' => now(),
        ]);

        if (! empty($data['roles'])) {
            $user->syncRoles([$data['roles'][0]]);
            $this->recordRoleAssignment($user, $data['roles'][0], $request, $data['role_reason'] ?? 'Initial assignment');
        }

        $this->audit->log('uam.user.create', $user, null, $user->only(['username', 'email', 'status', 'institution_id']));
        $this->notifications->notifyUser($user, 'NTF-UAM-001');
        if ($user->status === 'pending_approval') {
            $this->notifications->notifyUser($user, 'NTF-UAM-002');
        }

        return $user->load(['institution', 'unit', 'roles']);
    }

    public function activate(Request $request, User $user, ?string $reason = null): User
    {
        abort_unless($this->scope->canManageUser($request, $user), 403);
        $old = $user->status;
        $user->update([
            'status' => 'active',
            'suspension_reason' => null,
            'suspended_until' => null,
            'locked_at' => null,
            'failed_login_attempts' => 0,
            'deactivated_at' => null,
        ]);
        $this->audit->log('uam.user.activate', $user, ['status' => $old], ['status' => 'active', 'reason' => $reason]);
        $this->notifications->notifyUser($user, 'NTF-UAM-003');

        return $user->fresh(['institution', 'roles']);
    }

    public function suspend(Request $request, User $user, string $reason, ?string $until = null): User
    {
        abort_unless($this->scope->canManageUser($request, $user), 403);
        abort_if($request->user()?->id === $user->id, 422, 'You cannot suspend your own account.');
        abort_if($this->scope->isLastSystemAdmin($user), 422, 'Cannot suspend the last system administrator.');

        $old = $user->status;
        $user->update([
            'status' => 'suspended',
            'suspension_reason' => $reason,
            'suspended_until' => $until,
        ]);
        $this->revokeSessions($user);
        $this->audit->log('uam.user.suspend', $user, ['status' => $old], ['status' => 'suspended', 'reason' => $reason]);
        $this->notifications->notifyUser($user, 'NTF-UAM-010', ['reason' => $reason]);

        return $user->fresh(['institution', 'roles']);
    }

    public function deactivate(Request $request, User $user, string $reason): User
    {
        abort_unless($this->scope->canManageUser($request, $user), 403);
        abort_if($request->user()?->id === $user->id, 422, 'You cannot deactivate your own account.');
        abort_if($this->scope->isLastSystemAdmin($user), 422, 'Cannot deactivate the last system administrator.');

        $openCases = DB::table('complaints')
            ->where('current_handler_id', $user->id)
            ->whereNull('closed_at')
            ->count();

        $user->update([
            'status' => 'deactivated',
            'deactivated_at' => now(),
            'suspension_reason' => $reason,
        ]);
        $this->revokeSessions($user);
        $this->audit->log('uam.user.deactivate', $user, null, [
            'status' => 'deactivated',
            'reason' => $reason,
            'open_cases' => $openCases,
        ]);
        $this->notifications->notifyUser($user, 'NTF-UAM-017', ['reason' => $reason]);
        $this->notifications->notifyAdmins('NTF-UAM-044', [
            'user_name' => $user->displayName(),
            'reason' => $reason,
        ]);

        return $user->fresh(['institution', 'roles']);
    }

    public function unlock(Request $request, User $user): User
    {
        abort_unless($this->scope->canManageUser($request, $user), 403);
        $user->update([
            'status' => $user->status === 'locked' ? 'active' : $user->status,
            'locked_at' => null,
            'failed_login_attempts' => 0,
        ]);
        $this->audit->log('uam.user.unlock', $user);
        $this->notifications->notifyUser($user, 'NTF-UAM-011');

        return $user->fresh(['institution', 'roles']);
    }

    public function assignRole(Request $request, User $user, string $roleName, string $reason): User
    {
        abort_unless($this->scope->canManageUser($request, $user), 403);
        abort_unless($this->scope->canAssignRole($request, $roleName), 403);
        abort_if($request->user()?->id === $user->id && in_array($roleName, ['system_administrator', 'mtt_admin'], true), 422, 'Self-elevation is not permitted.');

        $oldRoles = $user->getRoleNames()->all();
        $user->syncRoles([$roleName]);
        $this->recordRoleAssignment($user, $roleName, $request, $reason);
        $this->revokeSessions($user);
        $this->audit->log('uam.user.role_change', $user, ['roles' => $oldRoles], ['roles' => [$roleName], 'reason' => $reason]);
        $this->notifications->notifyUser($user, 'NTF-UAM-005', ['new_role' => $roleName]);

        return $user->fresh(['institution', 'roles']);
    }

    public function resetPassword(Request $request, User $user, string $password): User
    {
        abort_unless($this->scope->canManageUser($request, $user), 403);
        $user->update([
            'password' => Hash::make($password),
            'password_must_change' => true,
        ]);
        $this->revokeSessions($user);
        $this->audit->log('uam.user.password_reset', $user);
        $this->notifications->notifyUser($user, 'NTF-UAM-007');

        return $user;
    }

    private function recordRoleAssignment(User $user, string $roleName, Request $request, string $reason): void
    {
        $role = Role::findByName($roleName, 'web');
        UserRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'ends_at' => now()]);

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'starts_at' => now(),
            'reason' => $reason,
            'assigned_by' => $request->user()?->id,
            'is_active' => true,
        ]);
    }

    private function revokeSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
