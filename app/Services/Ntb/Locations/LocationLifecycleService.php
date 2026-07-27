<?php

namespace App\Services\Ntb\Locations;

use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\Location;
use App\Services\Ntb\AuditLogger;
use Illuminate\Http\Request;

class LocationLifecycleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function activate(Request $request, Location $location): Location
    {
        $old = $location->is_active;
        $location->update([
            'is_active' => true,
            'deactivation_reason' => null,
            'deactivated_at' => null,
        ]);

        $this->audit->log('location.activate', $location, ['is_active' => $old], ['is_active' => true], $request);

        return $location->fresh();
    }

    public function deactivate(Request $request, Location $location, ?string $reason = null): Location
    {
        $impact = $this->impactAssessment($location);

        abort_if($impact['open_cases'] > 0, 422, "Cannot deactivate: {$impact['open_cases']} open case(s) reference this location.");

        $old = $location->is_active;
        $location->update([
            'is_active' => false,
            'deactivation_reason' => $reason,
            'deactivated_at' => now(),
        ]);

        $this->audit->log('location.deactivate', $location, ['is_active' => $old], [
            'is_active' => false,
            'reason' => $reason,
            'impact' => $impact,
        ], $request);

        return $location->fresh();
    }

    public function impactAssessment(Location $location): array
    {
        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        return [
            'open_cases' => Complaint::query()
                ->where('location_id', $location->id)
                ->whereNotIn('status_id', $terminalIds)
                ->count(),
            'total_cases' => Complaint::query()->where('location_id', $location->id)->count(),
            'has_coordinates' => $location->latitude !== null && $location->longitude !== null,
        ];
    }
}
