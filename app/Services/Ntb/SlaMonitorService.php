<?php

namespace App\Services\Ntb;

use App\Enums\ComplaintStatusCode;
use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\Escalation;
use App\Models\SlaTracking;
use Illuminate\Support\Facades\DB;

class SlaMonitorService
{
    public function __construct(
        private readonly ComplaintWorkflowService $workflow,
        private readonly NotificationService $notifications,
    ) {}

    public function run(): int
    {
        $processed = 0;

        SlaTracking::query()
            ->whereNull('completed_at')
            ->where('deadline_at', '<', now())
            ->where('is_breached', false)
            ->with('complaint.status')
            ->chunkById(50, function ($rows) use (&$processed) {
                foreach ($rows as $sla) {
                    $this->markBreached($sla);
                    $processed++;
                }
            });

        $this->autoEscalateOverdue();

        return $processed;
    }

    private function markBreached(SlaTracking $sla): void
    {
        $breachHours = (int) now()->diffInHours($sla->deadline_at, true);

        $sla->update([
            'is_breached' => true,
            'breach_hours' => $breachHours,
        ]);

        if ($sla->alert_sent_at) {
            return;
        }

        $complaint = $sla->complaint;

        if ($complaint) {
            $this->notifications->dispatch($complaint, 'sla_breach', [
                'stage' => $sla->stage,
                'breach_hours' => $breachHours,
            ]);
        }

        $sla->update(['alert_sent_at' => now()]);
    }

    private function autoEscalateOverdue(): void
    {
        $escalatedStatusId = ComplaintStatus::query()->where('code', ComplaintStatusCode::Escalated->value)->value('id');

        Complaint::query()
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '<', now())
            ->whereNotIn('status_id', function ($q) {
                $q->select('id')->from('complaint_statuses')
                    ->whereIn('code', [
                        ComplaintStatusCode::Closed->value,
                        ComplaintStatusCode::Archived->value,
                        ComplaintStatusCode::Rejected->value,
                        ComplaintStatusCode::Escalated->value,
                    ]);
            })
            ->chunkById(20, function ($complaints) use ($escalatedStatusId) {
                foreach ($complaints as $complaint) {
                    if ($complaint->status_id === $escalatedStatusId) {
                        continue;
                    }

                    DB::transaction(function () use ($complaint) {
                        Escalation::query()->create([
                            'complaint_id' => $complaint->id,
                            'escalation_level' => 1,
                            'escalation_type' => 'auto_sla',
                            'escalation_reason' => 'Automatic escalation due to SLA overrun',
                            'escalated_to_institution_id' => $complaint->assigned_institution_id,
                            'escalation_date' => now(),
                        ]);

                        if (in_array(ComplaintStatusCode::Escalated->value, $this->workflow->allowedTransitions($complaint), true)) {
                            $this->workflow->transition(
                                $complaint,
                                ComplaintStatusCode::Escalated->value,
                                null,
                                'Automatic escalation due to SLA overrun',
                            );
                            $this->notifications->dispatch($complaint->fresh(['status']), 'auto_escalation');
                        }
                    });
                }
            });
    }

    public function completeActiveStages(Complaint $complaint): void
    {
        SlaTracking::query()
            ->where('complaint_id', $complaint->id)
            ->whereNull('completed_at')
            ->update(['completed_at' => now()]);
    }
}
