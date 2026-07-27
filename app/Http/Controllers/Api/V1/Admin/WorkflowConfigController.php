<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\NotificationTemplate;
use App\Models\NtbCategory;
use App\Models\SlaConfiguration;
use App\Models\SlaTracking;
use App\Models\SystemSetting;
use App\Models\WorkflowTransition;
use App\Services\Ntb\AuditLogger;
use App\Services\Ntb\ComplaintWorkflowService;
use App\Services\Ntb\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class WorkflowConfigController extends Controller
{
    public function __construct(
        private readonly ComplaintWorkflowService $workflow,
        private readonly SystemSettingsService $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $terminal = ['closed', 'archived', 'rejected'];
        $openCases = Complaint::query()->whereHas('status', fn ($q) => $q->whereNotIn('code', $terminal))->count();
        $slaBreached = SlaTracking::query()->where('is_breached', true)->distinct('complaint_id')->count('complaint_id');
        $activeTransitions = WorkflowTransition::query()->where('is_active', true)->count();
        $slaRules = SlaConfiguration::query()->where('is_active', true)->count();
        $activeNotifications = NotificationTemplate::query()->where('is_active', true)->count();

        $stageBreaches = SlaTracking::query()
            ->select('stage')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_breached = 1 THEN 1 ELSE 0 END) as breached')
            ->groupBy('stage')
            ->orderBy('stage')
            ->get()
            ->map(fn ($row) => [
                'stage' => $row->stage,
                'total' => (int) $row->total,
                'breached' => (int) $row->breached,
                'compliance_pct' => $row->total > 0
                    ? round((($row->total - $row->breached) / $row->total) * 100, 1)
                    : 100,
            ]);

        $leaderboard = SlaTracking::query()
            ->whereNotNull('institution_id')
            ->with('institution:id,institution_name,institution_type')
            ->get(['id', 'institution_id', 'is_breached', 'stage'])
            ->groupBy('institution_id')
            ->map(function ($group) {
                $institution = $group->first()->institution;
                $total = $group->count();
                $breached = $group->where('is_breached', true)->count();

                return [
                    'institution' => $institution?->institution_name ?? '—',
                    'institution_type' => $institution?->institution_type,
                    'sla_stages' => $total,
                    'sla_breached' => $breached,
                    'sla_pct' => $total > 0 ? round((($total - $breached) / $total) * 100, 1) : 100,
                ];
            })
            ->sortByDesc('sla_stages')
            ->values()
            ->take(10);

        return response()->json([
            'summary' => [
                'open_cases' => $openCases,
                'sla_breached' => $slaBreached,
                'active_transitions' => $activeTransitions,
                'sla_rules' => $slaRules,
                'notification_templates' => $activeNotifications,
            ],
            'stage_breaches' => $stageBreaches,
            'leaderboard' => $leaderboard,
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $statuses = ComplaintStatus::query()->orderBy('sort_order')->get(['id', 'code', 'name_en', 'name_fr']);
        $categories = NtbCategory::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name_en', 'name_fr']);
        $permissions = Permission::query()
            ->where('name', 'like', 'complaints.%')
            ->orWhere('name', 'like', 'admin.%')
            ->orderBy('name')
            ->pluck('name');

        return response()->json([
            'statuses' => $statuses,
            'categories' => $categories,
            'permissions' => $permissions,
            'sla_stages' => ['VALIDATION', 'FIRST_RESPONSE', 'RESOLUTION'],
            'notification_channels' => ['email', 'sms'],
            'event_types' => [
                'complaint_created',
                'complaint_validated',
                'complaint_assigned',
                'complaint_status_changed',
                'complaint_closed',
                'complaint_reopened',
                'auto_escalation',
                'sla_breach',
            ],
        ]);
    }

    public function transitions(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        if ($request->isMethod('post')) {
            $data = $this->validateTransition($request);
            $row = WorkflowTransition::query()->create($data);
            $this->workflow->flushTransitionCache();
            $this->auditLogger->log('workflow.transition.created', $row, null, $row->toArray());

            return response()->json(['transition' => $row], 201);
        }

        $transitions = WorkflowTransition::query()->orderBy('from_status')->orderBy('sort_order')->get();
        $workflowMap = $transitions->groupBy('from_status')->map(fn ($items, $from) => [
            'from_status' => $from,
            'steps' => $items->values(),
        ])->values();

        return response()->json([
            'transitions' => $transitions,
            'workflow_map' => $workflowMap,
        ]);
    }

    public function updateTransition(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $row = WorkflowTransition::query()->findOrFail($id);
        $old = $row->toArray();
        $data = $this->validateTransition($request, updating: true);
        $row->update($data);
        $this->workflow->flushTransitionCache();
        $this->auditLogger->log('workflow.transition.updated', $row, $old, $row->fresh()->toArray());

        return response()->json(['transition' => $row]);
    }

    public function deleteTransition(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $row = WorkflowTransition::query()->findOrFail($id);
        $old = $row->toArray();
        $row->delete();
        $this->workflow->flushTransitionCache();
        $this->auditLogger->log('workflow.transition.deleted', null, $old, null);

        return response()->json(['message' => 'Transition removed']);
    }

    public function slaConfigurations(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        if ($request->isMethod('post')) {
            $data = $this->validateSla($request);
            $row = SlaConfiguration::query()->create($data);
            $this->auditLogger->log('workflow.sla.created', $row, null, $row->toArray());

            return response()->json(['configuration' => $row->load('category')], 201);
        }

        return response()->json([
            'configurations' => SlaConfiguration::query()->with('category')->orderBy('stage')->orderBy('ntb_category_id')->get(),
        ]);
    }

    public function updateSlaConfiguration(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $row = SlaConfiguration::query()->findOrFail($id);
        $old = $row->toArray();
        $data = $this->validateSla($request, updating: true);
        $row->update($data);
        $this->auditLogger->log('workflow.sla.updated', $row, $old, $row->fresh()->toArray());

        return response()->json(['configuration' => $row->load('category')]);
    }

    public function deleteSlaConfiguration(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $row = SlaConfiguration::query()->findOrFail($id);
        $old = $row->toArray();
        $row->delete();
        $this->auditLogger->log('workflow.sla.deleted', null, $old, null);

        return response()->json(['message' => 'SLA rule removed']);
    }

    public function notificationTemplates(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        if ($request->isMethod('post')) {
            $data = $this->validateNotification($request);
            $row = NotificationTemplate::query()->create($data);
            $this->auditLogger->log('workflow.notification.created', $row, null, $row->toArray());

            return response()->json(['template' => $row], 201);
        }

        return response()->json([
            'templates' => NotificationTemplate::query()->orderBy('event_type')->get(),
        ]);
    }

    public function updateNotificationTemplate(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $row = NotificationTemplate::query()->findOrFail($id);
        $old = $row->toArray();
        $data = $this->validateNotification($request, updating: true, existingId: $id);
        $row->update($data);
        $this->auditLogger->log('workflow.notification.updated', $row, $old, $row->fresh()->toArray());

        return response()->json(['template' => $row]);
    }

    public function deleteNotificationTemplate(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $row = NotificationTemplate::query()->findOrFail($id);
        $old = $row->toArray();
        $row->delete();
        $this->auditLogger->log('workflow.notification.deleted', null, $old, null);

        return response()->json(['message' => 'Notification template removed']);
    }

    public function systemSettings(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        if ($request->isMethod('put')) {
            $data = $request->validate([
                'max_attachment_size_kb' => ['integer', 'min:512', 'max:51200'],
                'archive_retention_years' => ['integer', 'min:1', 'max:50'],
                'duplicate_detection_days' => ['integer', 'min:1', 'max:90'],
                'sms_webhook_secret' => ['nullable', 'string', 'max:255'],
            ]);

            foreach ($data as $key => $value) {
                if ($value === null) {
                    continue;
                }
                $type = $key === 'sms_webhook_secret' ? 'string' : 'integer';
                SystemSetting::setValue($key, $value, $type);
            }

            $this->auditLogger->log('workflow.settings.updated', null, null, $data);

            return response()->json(['settings' => $this->settings->all()]);
        }

        return response()->json(['settings' => $this->settings->all()]);
    }

    /** @return array<string, mixed> */
    private function validateTransition(Request $request, bool $updating = false): array
    {
        $rules = [
            'from_status' => [$updating ? 'sometimes' : 'required', 'string', 'max:100'],
            'to_status' => [$updating ? 'sometimes' : 'required', 'string', 'max:100'],
            'label_en' => ['nullable', 'string', 'max:255'],
            'label_fr' => ['nullable', 'string', 'max:255'],
            'required_permission' => ['nullable', 'string', 'max:100'],
            'requires_comment' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ];

        $data = $request->validate($rules);
        $data['requires_comment'] = (bool) ($data['requires_comment'] ?? false);
        $data['is_active'] = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    /** @return array<string, mixed> */
    private function validateSla(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'stage' => [$updating ? 'sometimes' : 'required', 'string', 'max:100'],
            'ntb_category_id' => ['nullable', 'exists:ntb_categories,id'],
            'target_hours' => [$updating ? 'sometimes' : 'required', 'integer', 'min:1'],
            'alert_threshold_percent' => ['integer', 'min:1', 'max:100'],
            'is_active' => ['boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validateNotification(Request $request, bool $updating = false, ?int $existingId = null): array
    {
        $uniqueRule = 'unique:notification_templates,event_type';
        if ($updating && $existingId) {
            $uniqueRule .= ','.$existingId;
        }

        return $request->validate([
            'event_type' => [$updating ? 'sometimes' : 'required', 'string', 'max:100', $uniqueRule],
            'subject_en' => ['nullable', 'string', 'max:255'],
            'subject_fr' => ['nullable', 'string', 'max:255'],
            'body_en' => ['nullable', 'string'],
            'body_fr' => ['nullable', 'string'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:email,sms'],
            'is_active' => ['boolean'],
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->can('admin.workflows')
            || $request->user()?->can('admin.slas')
            || $request->user()?->can('admin.notifications'),
            403,
        );
    }
}
