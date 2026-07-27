<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ComplaintStatusCode;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Escalation;
use App\Models\Institution;
use App\Models\NtbCategory;
use App\Models\SlaTracking;
use App\Models\User;
use App\Services\Ntb\ComplaintWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintController extends Controller
{
    public function __construct(private readonly ComplaintWorkflowService $workflow) {}

    public function stats(Request $request): JsonResponse
    {
        $base = $this->scopedQuery($request);

        $terminal = ['closed', 'archived', 'rejected'];
        $open = (clone $base)->whereHas('status', fn ($q) => $q->whereNotIn('code', $terminal))->count();
        $validation = (clone $base)->whereHas('status', fn ($q) => $q->whereIn('code', ['submitted', 'under_validation', 'additional_info_requested']))->count();
        $closed = (clone $base)->whereHas('status', fn ($q) => $q->where('code', 'closed'))->count();
        $archived = (clone $base)->whereHas('status', fn ($q) => $q->where('code', 'archived'))->count();

        $complaintIds = (clone $base)->pluck('id');
        $slaBreached = SlaTracking::query()
            ->whereIn('complaint_id', $complaintIds)
            ->where('is_breached', true)
            ->distinct('complaint_id')
            ->count('complaint_id');

        $leaderboard = (clone $base)
            ->whereNotNull('assigned_institution_id')
            ->select('assigned_institution_id')
            ->selectRaw('COUNT(*) as total_cases')
            ->selectRaw("SUM(CASE WHEN EXISTS (
                SELECT 1 FROM complaint_statuses cs WHERE cs.id = complaints.status_id AND cs.code IN ('closed','archived','resolved')
            ) THEN 1 ELSE 0 END) as resolved_cases")
            ->selectRaw('SUM(CASE WHEN EXISTS (
                SELECT 1 FROM sla_trackings st WHERE st.complaint_id = complaints.id AND st.is_breached = 1
            ) THEN 1 ELSE 0 END) as sla_breached')
            ->groupBy('assigned_institution_id')
            ->with('assignedInstitution:id,institution_name,institution_type')
            ->orderByDesc('total_cases')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'institution' => $row->assignedInstitution?->institution_name ?? '—',
                'institution_type' => $row->assignedInstitution?->institution_type,
                'cases_assigned' => (int) $row->total_cases,
                'resolved' => (int) $row->resolved_cases,
                'sla_breached' => (int) $row->sla_breached,
                'sla_pct' => $row->total_cases > 0
                    ? round((($row->total_cases - $row->sla_breached) / $row->total_cases) * 100, 1)
                    : 100,
            ]);

        return response()->json([
            'summary' => [
                'open' => $open,
                'validation_queue' => $validation,
                'closed' => $closed,
                'archived' => $archived,
                'sla_breached' => $slaBreached,
            ],
            'leaderboard' => $leaderboard,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->scopedQuery($request)->with(['status', 'category', 'corridor', 'location', 'assignedInstitution', 'currentHandler']);

        if ($status = $request->string('status')->toString()) {
            $query->whereHas('status', fn ($q) => $q->where('code', $status));
        }

        if ($tab = $request->string('tab')->toString()) {
            match ($tab) {
                'active' => $query->whereHas('status', fn ($q) => $q->whereNotIn('code', ['closed', 'archived', 'rejected'])),
                'validation' => $query->whereHas('status', fn ($q) => $q->whereIn('code', ['submitted', 'under_validation', 'additional_info_requested'])),
                'closed' => $query->whereHas('status', fn ($q) => $q->whereIn('code', ['closed', 'archived'])),
                'sla_alerts' => $query->whereHas('slaTrackings', fn ($q) => $q->where('is_breached', true)),
                default => null,
            };
        }

        foreach (['category_id' => 'ntb_category_id', 'corridor_id' => 'corridor_id', 'institution_id' => 'assigned_institution_id'] as $param => $column) {
            if ($request->filled($param)) {
                $query->where($column, $request->integer($param));
            }
        }

        if ($priority = $request->string('priority')->toString()) {
            $query->where('priority', $priority);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('complaint_code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate($request->integer('per_page', 20)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $complaint = $this->scopedQuery($request)
            ->with([
                'status', 'category.responsibleInstitution', 'corridor', 'location',
                'assignedInstitution', 'currentHandler', 'histories.performer',
                'attachments', 'escalations', 'slaTrackings', 'notifications',
            ])
            ->findOrFail($id);

        $auditLogs = \App\Models\AuditLog::query()
            ->where('entity_type', Complaint::class)
            ->where('entity_id', $complaint->id)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'complaint' => $complaint,
            'allowed_transitions' => $this->workflow->allowedTransitions($complaint),
            'audit_logs' => $auditLogs,
        ]);
    }

    public function validateComplaint(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'complaints.validate');
        $data = $request->validate(['comment' => ['nullable', 'string']]);
        $complaint = $this->findScoped($request, $id);

        $target = $complaint->status->code === ComplaintStatusCode::Submitted->value
            ? ComplaintStatusCode::UnderValidation->value
            : ComplaintStatusCode::Assigned->value;

        $extra = $target === ComplaintStatusCode::Assigned->value
            ? $this->assignmentPayload($request, $complaint)
            : [];

        $updated = $this->workflow->transition(
            $complaint,
            $target,
            $request->user()->id,
            $data['comment'] ?? ($target === ComplaintStatusCode::Assigned->value ? 'Case validated and assigned' : 'Case moved to validation'),
            $extra,
        );

        return response()->json(['complaint' => $updated]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'complaints.validate');
        $data = $request->validate(['comment' => ['required', 'string']]);
        $complaint = $this->findScoped($request, $id);

        $updated = $this->workflow->transition(
            $complaint,
            ComplaintStatusCode::Rejected->value,
            $request->user()->id,
            $data['comment'],
        );

        return response()->json(['complaint' => $updated]);
    }

    public function requestInfo(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'complaints.validate');
        $data = $request->validate(['comment' => ['required', 'string']]);
        $complaint = $this->findScoped($request, $id);

        $updated = $this->workflow->transition(
            $complaint,
            ComplaintStatusCode::AdditionalInfoRequested->value,
            $request->user()->id,
            $data['comment'],
        );

        return response()->json(['complaint' => $updated]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'complaints.assign');
        $data = $request->validate([
            'assigned_institution_id' => ['required', 'exists:institutions,id'],
            'current_handler_id' => ['nullable', 'exists:users,id'],
            'comment' => ['nullable', 'string'],
        ]);

        $complaint = $this->findScoped($request, $id);
        $updated = $this->workflow->transition(
            $complaint,
            ComplaintStatusCode::Assigned->value,
            $request->user()->id,
            $data['comment'] ?? 'Case assigned',
            [
                'assigned_institution_id' => $data['assigned_institution_id'],
                'current_handler_id' => $data['current_handler_id'] ?? null,
            ],
        );

        return response()->json(['complaint' => $updated]);
    }

    public function process(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'complaints.process');
        $data = $request->validate(['comment' => ['nullable', 'string']]);
        $complaint = $this->findScoped($request, $id);

        $updated = $this->workflow->transition(
            $complaint,
            ComplaintStatusCode::InProcessing->value,
            $request->user()->id,
            $data['comment'] ?? null,
        );

        return response()->json(['complaint' => $updated]);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'complaints.process');
        $data = $request->validate([
            'resolution_note' => ['required', 'string'],
            'comment' => ['nullable', 'string'],
        ]);
        $complaint = $this->findScoped($request, $id);

        $updated = $this->workflow->transition(
            $complaint,
            ComplaintStatusCode::Resolved->value,
            $request->user()->id,
            $data['comment'] ?? 'Resolution proposed',
            ['resolution_note' => $data['resolution_note']],
        );

        return response()->json(['complaint' => $updated]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'complaints.close');
        $data = $request->validate([
            'comment' => ['nullable', 'string'],
            'closure_summary' => ['nullable', 'string'],
        ]);
        $complaint = $this->findScoped($request, $id);
        $this->assertResponsibleInstitution($request, $complaint);

        $updated = $this->workflow->transition(
            $complaint,
            ComplaintStatusCode::Closed->value,
            $request->user()->id,
            $data['comment'] ?? 'Case closed',
            ['closure_summary' => $data['closure_summary'] ?? $data['comment'] ?? null],
        );

        return response()->json(['complaint' => $updated]);
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'complaints.close');
        $data = $request->validate(['comment' => ['nullable', 'string']]);
        $complaint = $this->findScoped($request, $id);

        if ($complaint->status->code !== ComplaintStatusCode::Closed->value) {
            abort(422, 'Only closed cases can be archived.');
        }

        $updated = $this->workflow->transition(
            $complaint,
            ComplaintStatusCode::Archived->value,
            $request->user()->id,
            $data['comment'] ?? 'Case archived for retention',
        );

        return response()->json(['complaint' => $updated]);
    }

    public function reopen(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'complaints.reopen');
        $data = $request->validate(['comment' => ['required', 'string']]);
        $complaint = $this->findScoped($request, $id);

        $updated = $this->workflow->transition(
            $complaint,
            ComplaintStatusCode::Reopened->value,
            $request->user()->id,
            $data['comment'],
            ['closed_at' => null, 'archived_at' => null],
        );

        return response()->json(['complaint' => $updated]);
    }

    public function escalate(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'complaints.escalate');
        $data = $request->validate([
            'escalation_level' => ['required', 'integer', 'min:1', 'max:5'],
            'escalation_type' => ['required', 'string', 'max:50'],
            'escalation_reason' => ['required', 'string'],
            'escalated_to_user_id' => ['nullable', 'exists:users,id'],
            'escalated_to_institution_id' => ['nullable', 'exists:institutions,id'],
        ]);

        $complaint = $this->findScoped($request, $id);

        Escalation::query()->create([
            'complaint_id' => $complaint->id,
            'escalation_level' => $data['escalation_level'],
            'escalation_type' => $data['escalation_type'],
            'escalation_reason' => $data['escalation_reason'],
            'escalated_to_user_id' => $data['escalated_to_user_id'] ?? null,
            'escalated_to_institution_id' => $data['escalated_to_institution_id'] ?? null,
            'escalation_date' => now(),
        ]);

        $updated = $this->workflow->transition(
            $complaint,
            ComplaintStatusCode::Escalated->value,
            $request->user()->id,
            $data['escalation_reason'],
        );

        return response()->json(['complaint' => $updated]);
    }

    public function comment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['comment' => ['required', 'string']]);
        $complaint = $this->findScoped($request, $id);

        $history = $this->workflow->addComment(
            $complaint,
            $request->user()->id,
            $data['comment'],
            $request->user()->institution_id,
        );

        return response()->json(['history' => $history]);
    }

    public function downloadAttachment(Request $request, int $id, int $attachmentId): StreamedResponse
    {
        $complaint = $this->findScoped($request, $id);
        $attachment = $complaint->attachments()->findOrFail($attachmentId);

        abort_unless(Storage::disk('public')->exists($attachment->file_path), 404);

        return Storage::disk('public')->download($attachment->file_path, $attachment->original_name);
    }

    public function lookups(Request $request): JsonResponse
    {
        return response()->json([
            'categories' => NtbCategory::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name_en', 'name_fr']),
            'institutions' => Institution::query()->orderBy('institution_name')->get(['id', 'institution_name']),
            'handlers' => User::query()->whereNotNull('institution_id')->orderBy('name')->get(['id', 'name', 'institution_id']),
        ]);
    }

    private function scopedQuery(Request $request)
    {
        $query = Complaint::query();
        $user = $request->user();

        if ($user->can('complaints.view_all')) {
            return $query;
        }

        if ($user->can('complaints.view_own')) {
            return $query->where('created_by', $user->id);
        }

        if ($user->institution_id) {
            return $query->where('assigned_institution_id', $user->institution_id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function findScoped(Request $request, int $id): Complaint
    {
        return $this->scopedQuery($request)->with('status')->findOrFail($id);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403);
    }

    private function assertResponsibleInstitution(Request $request, Complaint $complaint): void
    {
        $user = $request->user();

        if ($user->can('complaints.view_all') && ! $user->institution_id) {
            return;
        }

        if ($user->can('complaints.view_all') && in_array('national_focal_point', $user->getRoleNames()->all(), true)) {
            return;
        }

        if ($complaint->assigned_institution_id && $user->institution_id !== $complaint->assigned_institution_id) {
            abort(403, 'Only the responsible institution may close this case.');
        }
    }

    private function assignmentPayload(Request $request, Complaint $complaint): array
    {
        $categoryInstitution = $complaint->category?->responsible_institution_id;

        return [
            'assigned_institution_id' => $request->input('assigned_institution_id', $categoryInstitution),
        ];
    }
}
