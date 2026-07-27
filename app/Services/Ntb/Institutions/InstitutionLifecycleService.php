<?php

namespace App\Services\Ntb\Institutions;

use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\Institution;
use App\Models\User;
use App\Services\Ntb\AuditLogger;
use App\Services\Ntb\Uam\UamNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstitutionLifecycleService
{
    public function __construct(
        private readonly InstitutionScopeService $scope,
        private readonly AuditLogger $audit,
        private readonly UamNotificationService $notifications,
    ) {}

    public function activate(Request $request, Institution $institution, ?string $reason = null): Institution
    {
        abort_unless($this->scope->canManage($request), 403);

        $old = $institution->status;
        $institution->update([
            'status' => 'active',
            'suspension_reason' => null,
            'suspended_at' => null,
            'deactivated_at' => null,
        ]);

        $this->audit->log('institution.activate', $institution, ['status' => $old], ['status' => 'active', 'reason' => $reason], $request);
        $this->notifyInstitutionUsers($institution, 'NTF-UAM-011');

        return $institution->fresh();
    }

    public function suspend(Request $request, Institution $institution, string $reason): Institution
    {
        abort_unless($this->scope->canManage($request), 403);

        $old = $institution->status;
        $institution->update([
            'status' => 'suspended',
            'suspension_reason' => $reason,
            'suspended_at' => now(),
        ]);

        $this->audit->log('institution.suspend', $institution, ['status' => $old], ['status' => 'suspended', 'reason' => $reason], $request);
        $this->notifications->notifyAdmins('NTF-UAM-020', [
            'institution_name' => $institution->institution_name,
            'reason' => $reason,
        ], ['system_administrator', 'mtt_admin']);

        return $institution->fresh();
    }

    public function deactivate(Request $request, Institution $institution, string $reason): array
    {
        abort_unless($this->scope->canManage($request), 403);

        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');
        $openCases = Complaint::query()
            ->where('assigned_institution_id', $institution->id)
            ->whereNotIn('status_id', $terminalIds)
            ->count();

        $activeUsers = User::query()
            ->where('institution_id', $institution->id)
            ->where('status', 'active')
            ->count();

        abort_if($openCases > 0, 422, "Cannot deactivate: {$openCases} open case(s) must be reassigned first.");

        $institution->update([
            'status' => 'inactive',
            'deactivated_at' => now(),
            'suspension_reason' => $reason,
        ]);

        User::query()
            ->where('institution_id', $institution->id)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        $this->audit->log('institution.deactivate', $institution, null, [
            'status' => 'inactive',
            'reason' => $reason,
            'open_cases' => $openCases,
            'active_users' => $activeUsers,
        ], $request);

        return [
            'institution' => $institution->fresh(),
            'impact' => ['open_cases' => $openCases, 'users_suspended' => $activeUsers],
        ];
    }

    public function impactAssessment(Institution $institution): array
    {
        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        return [
            'open_cases' => Complaint::query()
                ->where('assigned_institution_id', $institution->id)
                ->whereNotIn('status_id', $terminalIds)
                ->count(),
            'active_users' => User::query()
                ->where('institution_id', $institution->id)
                ->where('status', 'active')
                ->count(),
            'focal_points' => User::query()
                ->where('institution_id', $institution->id)
                ->where('is_focal_point', true)
                ->where('status', 'active')
                ->count(),
            'units' => $institution->units()->where('is_active', true)->count(),
        ];
    }

    private function notifyInstitutionUsers(Institution $institution, string $event): void
    {
        User::query()
            ->where('institution_id', $institution->id)
            ->where('status', 'active')
            ->each(fn (User $user) => $this->notifications->notifyUser($user, $event));
    }
}
