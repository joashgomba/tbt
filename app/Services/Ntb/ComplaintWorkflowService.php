<?php

namespace App\Services\Ntb;

use App\Enums\ComplaintStatusCode;
use App\Models\CaseHistory;
use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\NtbCategory;
use App\Models\SlaConfiguration;
use App\Models\SlaTracking;
use App\Models\WorkflowTransition;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ComplaintWorkflowService
{
    /** @var array<string, list<string>> */
    private const DEFAULT_TRANSITIONS = [
        ComplaintStatusCode::Submitted->value => [ComplaintStatusCode::UnderValidation->value],
        ComplaintStatusCode::UnderValidation->value => [
            ComplaintStatusCode::Rejected->value,
            ComplaintStatusCode::AdditionalInfoRequested->value,
            ComplaintStatusCode::Assigned->value,
        ],
        ComplaintStatusCode::AdditionalInfoRequested->value => [ComplaintStatusCode::UnderValidation->value],
        ComplaintStatusCode::Assigned->value => [ComplaintStatusCode::InProcessing->value],
        ComplaintStatusCode::InProcessing->value => [
            ComplaintStatusCode::AdditionalInfoRequested->value,
            ComplaintStatusCode::AwaitingExternalInput->value,
            ComplaintStatusCode::Escalated->value,
            ComplaintStatusCode::Resolved->value,
        ],
        ComplaintStatusCode::AwaitingExternalInput->value => [ComplaintStatusCode::InProcessing->value],
        ComplaintStatusCode::Escalated->value => [
            ComplaintStatusCode::InProcessing->value,
            ComplaintStatusCode::Resolved->value,
        ],
        ComplaintStatusCode::Resolved->value => [
            ComplaintStatusCode::Closed->value,
            ComplaintStatusCode::Reopened->value,
        ],
        ComplaintStatusCode::Reopened->value => [ComplaintStatusCode::InProcessing->value],
        ComplaintStatusCode::Closed->value => [
            ComplaintStatusCode::Archived->value,
            ComplaintStatusCode::Reopened->value,
        ],
    ];

    /** @var array<string, list<string>>|null */
    private ?array $transitionMap = null;

    public function __construct(
        private readonly ComplaintCodeGenerator $codeGenerator,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function create(array $data, ?int $userId = null): Complaint
    {
        return DB::transaction(function () use ($data, $userId) {
            $status = ComplaintStatus::query()->where('code', ComplaintStatusCode::Submitted->value)->firstOrFail();
            $category = NtbCategory::query()->findOrFail($data['ntb_category_id']);
            $slaDeadline = now()->addWeekdays($category->default_sla_days);

            $complaint = Complaint::query()->create([
                ...$data,
                'complaint_code' => $this->codeGenerator->generate(),
                'status_id' => $status->id,
                'created_by' => $userId,
                'sla_deadline' => $slaDeadline,
            ]);

            $this->recordHistory($complaint, 'SUBMITTED', null, $status->code, $userId, 'Complaint submitted');
            $this->startSla($complaint, 'VALIDATION', $this->slaHours('VALIDATION', $category->id, 16), $complaint->assigned_institution_id);
            $this->auditLogger->log('complaint.created', $complaint, null, $complaint->toArray());
            $this->notifications->dispatch($complaint->fresh(['status']), 'complaint_created');

            return $complaint->load(['category', 'status', 'corridor', 'location']);
        });
    }

    public function transition(
        Complaint $complaint,
        string $toStatusCode,
        ?int $userId = null,
        ?string $comment = null,
        array $extra = [],
    ): Complaint {
        $fromCode = $complaint->status->code;
        $allowed = $this->allowedTransitions($complaint);

        if (! in_array($toStatusCode, $allowed, true)) {
            throw new InvalidArgumentException("Transition from {$fromCode} to {$toStatusCode} is not allowed.");
        }

        $requiresComment = $this->transitionRequiresComment($fromCode, $toStatusCode);

        if ($requiresComment && blank($comment)) {
            throw new InvalidArgumentException('A comment is required for this transition.');
        }

        return DB::transaction(function () use ($complaint, $fromCode, $toStatusCode, $userId, $comment, $extra) {
            $toStatus = ComplaintStatus::query()->where('code', $toStatusCode)->firstOrFail();
            $old = $complaint->only(['status_id', 'assigned_institution_id', 'current_handler_id', 'resolution_note', 'closed_at', 'archived_at']);

            $updates = array_merge(['status_id' => $toStatus->id], $extra);

            if ($toStatusCode === ComplaintStatusCode::Closed->value) {
                $updates['closed_at'] = now();
            }

            if ($toStatusCode === ComplaintStatusCode::Archived->value) {
                $updates['archived_at'] = now();
            }

            if ($toStatusCode === ComplaintStatusCode::Reopened->value) {
                $updates['closed_at'] = null;
                $updates['archived_at'] = null;
            }

            $complaint->update($updates);
            $this->completeOpenSlaStages($complaint);

            $this->recordHistory($complaint, 'STATUS_CHANGED', $fromCode, $toStatusCode, $userId, $comment);
            $this->auditLogger->log('complaint.status_changed', $complaint, $old, $complaint->fresh()->toArray());
            $this->dispatchStatusNotification($complaint->fresh(['status']), $fromCode, $toStatusCode);

            if ($toStatusCode === ComplaintStatusCode::Assigned->value) {
                $this->startSla($complaint, 'FIRST_RESPONSE', $this->slaHours('FIRST_RESPONSE', $complaint->ntb_category_id, 8), $complaint->assigned_institution_id);
            }

            if ($toStatusCode === ComplaintStatusCode::InProcessing->value) {
                $hours = $complaint->category->default_sla_days * 8;
                $this->startSla($complaint, 'RESOLUTION', $this->slaHours('RESOLUTION', $complaint->ntb_category_id, $hours), $complaint->assigned_institution_id);
            }

            return $complaint->fresh(['category', 'status', 'corridor', 'location', 'assignedInstitution', 'currentHandler']);
        });
    }

    public function addComment(Complaint $complaint, int $userId, string $comment, ?int $institutionId = null): CaseHistory
    {
        $history = $this->recordHistory(
            $complaint,
            'COMMENTED',
            $complaint->status->code,
            $complaint->status->code,
            $userId,
            $comment,
            $institutionId,
        );

        $this->auditLogger->log('complaint.commented', $complaint, null, ['comment' => $comment]);

        return $history;
    }

    public function allowedTransitions(Complaint $complaint): array
    {
        $map = $this->transitionsMap();

        return $map[$complaint->status->code] ?? [];
    }

    /** @return array<string, list<string>> */
    public function transitionsMap(): array
    {
        if ($this->transitionMap !== null) {
            return $this->transitionMap;
        }

        $db = WorkflowTransition::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($db->isEmpty()) {
            return $this->transitionMap = self::DEFAULT_TRANSITIONS;
        }

        $map = [];

        foreach ($db as $row) {
            $map[$row->from_status][] = $row->to_status;
        }

        return $this->transitionMap = $map;
    }

    public function flushTransitionCache(): void
    {
        $this->transitionMap = null;
    }

    private function transitionRequiresComment(string $from, string $to): bool
    {
        $row = WorkflowTransition::query()
            ->where('from_status', $from)
            ->where('to_status', $to)
            ->where('is_active', true)
            ->first();

        if ($row) {
            return $row->requires_comment;
        }

        return in_array($to, [
            ComplaintStatusCode::Rejected->value,
            ComplaintStatusCode::Escalated->value,
            ComplaintStatusCode::Reopened->value,
        ], true);
    }

    private function dispatchStatusNotification(Complaint $complaint, string $from, string $to): void
    {
        $map = [
            ComplaintStatusCode::UnderValidation->value => 'complaint_validated',
            ComplaintStatusCode::Assigned->value => 'complaint_assigned',
            ComplaintStatusCode::Closed->value => 'complaint_closed',
            ComplaintStatusCode::Reopened->value => 'complaint_reopened',
        ];

        $event = $map[$to] ?? 'complaint_status_changed';

        $this->notifications->dispatch($complaint, $event, [
            'from_status' => $from,
            'to_status' => $to,
        ]);
    }

    private function slaHours(string $stage, int $categoryId, int $fallback): int
    {
        $config = SlaConfiguration::query()
            ->where('stage', $stage)
            ->where(function ($q) use ($categoryId) {
                $q->whereNull('ntb_category_id')->orWhere('ntb_category_id', $categoryId);
            })
            ->where('is_active', true)
            ->orderByRaw('ntb_category_id IS NULL')
            ->first();

        return $config?->target_hours ?? $fallback;
    }

    private function completeOpenSlaStages(Complaint $complaint): void
    {
        SlaTracking::query()
            ->where('complaint_id', $complaint->id)
            ->whereNull('completed_at')
            ->update(['completed_at' => now()]);
    }

    private function recordHistory(
        Complaint $complaint,
        string $action,
        ?string $from,
        ?string $to,
        ?int $userId,
        ?string $comment,
        ?int $institutionId = null,
    ): CaseHistory {
        return CaseHistory::query()->create([
            'complaint_id' => $complaint->id,
            'action_type' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'performed_by' => $userId,
            'institution_id' => $institutionId ?? $complaint->assigned_institution_id,
            'comment' => $comment,
            'action_date' => now(),
            'ip_address' => request()->ip(),
        ]);
    }

    private function startSla(Complaint $complaint, string $stage, int $hours, ?int $institutionId): SlaTracking
    {
        return SlaTracking::query()->create([
            'complaint_id' => $complaint->id,
            'stage' => $stage,
            'sla_target_hours' => $hours,
            'started_at' => now(),
            'deadline_at' => now()->addHours($hours),
            'institution_id' => $institutionId,
        ]);
    }
}
