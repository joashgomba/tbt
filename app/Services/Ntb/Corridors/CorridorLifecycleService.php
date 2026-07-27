<?php

namespace App\Services\Ntb\Corridors;

use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\Corridor;
use App\Models\Location;
use App\Services\Ntb\AuditLogger;
use Illuminate\Http\Request;

class CorridorLifecycleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function activate(Request $request, Corridor $corridor): Corridor
    {
        $old = $corridor->active;
        $corridor->update([
            'active' => true,
            'deactivation_reason' => null,
            'deactivated_at' => null,
        ]);

        $this->audit->log('corridor.activate', $corridor, ['active' => $old], ['active' => true], $request);

        return $corridor->fresh();
    }

    public function deactivate(Request $request, Corridor $corridor, ?string $reason = null): Corridor
    {
        $impact = $this->impactAssessment($corridor);

        abort_if($impact['open_cases'] > 0, 422, "Cannot deactivate: {$impact['open_cases']} open case(s) are linked to this corridor.");

        $locationOpenCases = Complaint::query()
            ->whereIn('location_id', Location::query()->where('corridor_id', $corridor->id)->pluck('id'))
            ->whereNotIn('status_id', ComplaintStatus::query()->where('is_terminal', true)->pluck('id'))
            ->count();
        abort_if($locationOpenCases > 0, 422, "Cannot deactivate: {$locationOpenCases} open case(s) are linked to corridor locations.");

        $old = $corridor->active;
        $corridor->update([
            'active' => false,
            'deactivation_reason' => $reason,
            'deactivated_at' => now(),
        ]);

        $this->audit->log('corridor.deactivate', $corridor, ['active' => $old], [
            'active' => false,
            'reason' => $reason,
            'impact' => $impact,
        ], $request);

        return $corridor->fresh();
    }

    public function impactAssessment(Corridor $corridor): array
    {
        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        return [
            'open_cases' => Complaint::query()
                ->where('corridor_id', $corridor->id)
                ->whereNotIn('status_id', $terminalIds)
                ->count(),
            'total_cases' => Complaint::query()->where('corridor_id', $corridor->id)->count(),
            'locations' => Location::query()->where('corridor_id', $corridor->id)->count(),
            'border_posts' => Location::query()->where('corridor_id', $corridor->id)->where('border_post', true)->count(),
            'linked_institutions' => $corridor->institutions()->count(),
        ];
    }
}
