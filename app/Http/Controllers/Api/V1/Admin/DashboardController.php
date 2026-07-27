<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\Corridor;
use App\Models\Institution;
use App\Models\Location;
use App\Models\NtbCategory;
use App\Models\SlaTracking;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $baseQuery = $this->scopedComplaintsQuery($request);
        $query = $this->applyDashboardFilters(clone $baseQuery, $request);

        $complaintIds = (clone $query)->pluck('id');
        $total = $complaintIds->count();

        $openStatusIds = ComplaintStatus::query()->where('is_terminal', false)->pluck('id');
        $closedStatusIds = ComplaintStatus::query()->whereIn('code', ['closed', 'archived', 'rejected'])->pluck('id');
        $resolvedStatusIds = ComplaintStatus::query()->whereIn('code', ['resolved', 'closed', 'archived'])->pluck('id');

        $open = (clone $query)->whereIn('status_id', $openStatusIds)->count();
        $closed = (clone $query)->whereIn('status_id', $closedStatusIds)->count();

        $avgResolutionHours = (clone $query)
            ->whereNotNull('closed_at')
            ->whereIn('status_id', $resolvedStatusIds)
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, closed_at)) as avg_hours')
            ->value('avg_hours');

        $breached = SlaTracking::query()
            ->where('is_breached', true)
            ->whereIn('complaint_id', $complaintIds)
            ->count();

        $slaTotal = SlaTracking::query()->whereIn('complaint_id', $complaintIds)->count();
        $slaCompliance = $slaTotal > 0 ? round((($slaTotal - $breached) / $slaTotal) * 100, 1) : 100;

        $byMonth = (clone $query)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $year = (int) ($request->input('year') ?: now()->year);
        $trendYear = (clone $query)
            ->whereYear('created_at', $year)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $trendFilled = collect(range(1, 12))->map(function ($m) use ($year, $trendYear) {
            $key = sprintf('%04d-%02d', $year, $m);

            return ['month' => Carbon::create($year, $m)->format('M'), 'total' => (int) ($trendYear[$key]->total ?? 0)];
        });

        $byCategory = (clone $query)
            ->select('ntb_category_id', DB::raw('count(*) as count'))
            ->groupBy('ntb_category_id')
            ->with('category:id,code,name_en,name_fr')
            ->orderByDesc('count')
            ->get();

        $byStatus = ComplaintStatus::query()
            ->withCount(['complaints' => fn ($q) => $q->whereIn('complaints.id', $complaintIds)])
            ->having('complaints_count', '>', 0)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name_en', 'name_fr']);

        $byCorridor = (clone $query)
            ->select('corridor_id', DB::raw('count(*) as count'))
            ->groupBy('corridor_id')
            ->with('corridor:id,corridor_code,name_en,name_fr')
            ->orderByDesc('count')
            ->get();

        $resolvedList = $resolvedStatusIds->implode(',');

        $leaderboard = (clone $query)
            ->whereNotNull('assigned_institution_id')
            ->select('assigned_institution_id')
            ->selectRaw('COUNT(*) as total_cases')
            ->selectRaw("SUM(CASE WHEN status_id IN ({$resolvedList}) THEN 1 ELSE 0 END) as resolved_cases")
            ->selectRaw("SUM(CASE WHEN status_id IN ({$openStatusIds->implode(',')}) THEN 1 ELSE 0 END) as open_cases")
            ->selectRaw('AVG(CASE WHEN closed_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, closed_at) END) as avg_resolution_hours')
            ->groupBy('assigned_institution_id')
            ->with('assignedInstitution:id,institution_name,institution_type')
            ->orderByDesc('total_cases')
            ->limit(15)
            ->get()
            ->map(function ($row) use ($complaintIds) {
                $institutionId = $row->assigned_institution_id;
                $slaBreached = SlaTracking::query()
                    ->where('is_breached', true)
                    ->where('institution_id', $institutionId)
                    ->whereIn('complaint_id', $complaintIds)
                    ->count();

                $total = (int) $row->total_cases;
                $resolved = (int) $row->resolved_cases;

                return [
                    'institution' => $row->assignedInstitution,
                    'total_cases' => $total,
                    'open_cases' => (int) $row->open_cases,
                    'resolved_cases' => $resolved,
                    'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 1) : 0,
                    'avg_resolution_days' => $row->avg_resolution_hours
                        ? round(((float) $row->avg_resolution_hours) / 24, 1)
                        : null,
                    'sla_breached' => $slaBreached,
                ];
            })
            ->values();

        $mapPoints = Location::query()
            ->withCount(['complaints' => fn ($q) => $q->whereIn('complaints.id', $complaintIds)])
            ->having('complaints_count', '>', 0)
            ->get()
            ->map(fn (Location $loc) => [
                'label' => $loc->location_name,
                'count' => $loc->complaints_count,
                'latitude' => (float) ($loc->latitude ?? 11.58),
                'longitude' => (float) ($loc->longitude ?? 43.15),
            ]);

        if ($mapPoints->isEmpty()) {
            $mapPoints = (clone $query)
                ->select('country_origin as label', DB::raw('count(*) as count'))
                ->groupBy('country_origin')
                ->get()
                ->map(fn ($row) => [
                    'label' => $row->label,
                    'count' => $row->count,
                    'latitude' => $this->countryLat($row->label),
                    'longitude' => $this->countryLng($row->label),
                ]);
        }

        return response()->json([
            'summary' => [
                'total' => $total,
                'open' => $open,
                'closed' => $closed,
                'resolution_rate' => $total > 0 ? round(($closed / $total) * 100, 1) : 0,
                'sla_breached' => $breached,
                'sla_compliance' => $slaCompliance,
                'avg_resolution_days' => $avgResolutionHours
                    ? round(((float) $avgResolutionHours) / 24, 1)
                    : null,
            ],
            'by_month' => $byMonth,
            'trend_year' => $trendFilled,
            'by_status' => $byStatus,
            'by_category' => $byCategory,
            'by_corridor' => $byCorridor,
            'leaderboard' => $leaderboard,
            'institution_performance' => [
                'overall' => $leaderboard->count() > 0
                    ? round($leaderboard->avg('resolution_rate'), 1)
                    : 0,
                'overall_resolution_rate' => $leaderboard->count() > 0
                    ? round($leaderboard->avg('resolution_rate'), 1)
                    : 0,
                'top' => $leaderboard->map(fn ($row) => [
                    'institution' => $row['institution']?->institution_name ?? '—',
                    'institution_type' => $row['institution']?->institution_type,
                    'cases_assigned' => $row['total_cases'],
                    'resolved' => $row['resolved_cases'],
                    'pct' => $row['resolution_rate'],
                ])->values(),
            ],
            'map_points' => $mapPoints,
            'recent' => (clone $query)
                ->with(['status', 'category', 'assignedInstitution', 'corridor'])
                ->latest()
                ->limit(12)
                ->get(),
            'filter_options' => [
                'categories' => NtbCategory::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name_en', 'name_fr']),
                'corridors' => Corridor::query()->where('active', true)->orderBy('corridor_code')->get(['id', 'corridor_code', 'name_en', 'name_fr']),
                'institutions' => Institution::query()->where('status', 'active')->orderBy('institution_name')->get(['id', 'institution_name']),
            ],
        ]);
    }

    private function applyDashboardFilters(Builder $query, Request $request): Builder
    {
        $fromMonth = $request->input('from_month');
        $toMonth = $request->input('to_month');
        $year = $request->input('year');

        if ($fromMonth && $toMonth) {
            $query->whereBetween('created_at', [
                Carbon::parse($fromMonth)->startOfMonth(),
                Carbon::parse($toMonth)->endOfMonth(),
            ]);
        } elseif ($year) {
            $query->whereYear('created_at', (int) $year);
        }

        if ($request->filled('category_id')) {
            $query->where('ntb_category_id', $request->integer('category_id'));
        }

        if ($request->filled('corridor_id')) {
            $query->where('corridor_id', $request->integer('corridor_id'));
        }

        if ($request->filled('institution_id')) {
            $query->where('assigned_institution_id', $request->integer('institution_id'));
        }

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->integer('status_id'));
        }

        return $query;
    }

    private function scopedComplaintsQuery(Request $request): Builder
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

    private function countryLat(?string $country): float
    {
        return match (strtolower(trim((string) $country))) {
            'ethiopia' => 9.03,
            'djibouti' => 11.58,
            default => 9.5,
        };
    }

    private function countryLng(?string $country): float
    {
        return match (strtolower(trim((string) $country))) {
            'ethiopia' => 38.74,
            'djibouti' => 43.15,
            default => 42.0,
        };
    }
}
