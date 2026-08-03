<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\ActivitySector;
use App\Models\Complaint;
use App\Models\Institution;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicTransparencyController extends Controller
{
    private const STATUS_GROUPS = [
        'received' => ['submitted', 'under_validation'],
        'under_review' => [
            'assigned', 'in_processing', 'additional_info_requested',
            'awaiting_external_input', 'escalated', 'reopened',
        ],
        'resolved' => ['resolved'],
        'closed' => ['closed', 'archived', 'rejected'],
    ];

    private const RESOLVED_CODES = ['resolved', 'closed', 'archived'];

    public function lookups(): JsonResponse
    {
        $years = Complaint::query()
            ->selectRaw('DISTINCT YEAR(created_at) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->values();

        if ($years->isEmpty()) {
            $years = collect([(int) date('Y')]);
        }

        $currentYear = (int) date('Y');
        for ($y = $currentYear; $y <= $currentYear + 2; $y++) {
            if (! $years->contains($y)) {
                $years->prepend($y);
            }
        }

        $years = $years->unique()->sortDesc()->values();

        return response()->json([
            'years' => $years,
            'institutions' => Institution::query()
                ->where('status', 'active')
                ->where(function (Builder $query) {
                    $query->whereHas('complaints')
                        ->orWhereHas('ntbCategories');
                })
                ->orderBy('institution_name')
                ->get(['id', 'institution_name', 'name_en', 'name_fr']),
            'activity_sectors' => ActivitySector::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(['id', 'code', 'name_en', 'name_fr']),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        $baseQuery = $this->filteredQuery($filters);

        $total = (clone $baseQuery)->count();

        $resolvedOrClosed = (clone $baseQuery)
            ->whereHas('status', fn ($q) => $q->whereIn('code', self::RESOLVED_CODES))
            ->count();

        $avgResolutionHours = (clone $baseQuery)
            ->whereNotNull('closed_at')
            ->whereHas('status', fn ($q) => $q->whereIn('code', self::RESOLVED_CODES))
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, closed_at)) as avg_hours')
            ->value('avg_hours');

        $byStatusGroup = [];
        foreach (self::STATUS_GROUPS as $group => $codes) {
            $byStatusGroup[$group] = (clone $baseQuery)
                ->whereHas('status', fn ($q) => $q->whereIn('code', $codes))
                ->count();
        }

        $byCategory = (clone $baseQuery)
            ->select('ntb_category_id', DB::raw('count(*) as count'))
            ->groupBy('ntb_category_id')
            ->with('category:id,code,name_en,name_fr')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'count' => $row->count,
            ]);

        $byCountry = (clone $baseQuery)
            ->select('country_origin as country', DB::raw('count(*) as count'))
            ->groupBy('country_origin')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return response()->json([
            'summary' => [
                'total_received' => $total,
                'resolved' => $resolvedOrClosed,
                'resolution_rate' => $total > 0 ? round(($resolvedOrClosed / $total) * 100, 1) : 0,
                'avg_resolution_days' => $avgResolutionHours
                    ? round(((float) $avgResolutionHours) / 24, 1)
                    : null,
            ],
            'by_status_group' => $byStatusGroup,
            'by_category' => $byCategory,
            'by_country' => $byCountry,
            'filters_applied' => $this->hasActiveFilters($filters),
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        $reports = $this->filteredQuery($filters)
            ->with([
                'category:id,code,name_en,name_fr',
                'activitySector:id,code,name_en,name_fr',
                'status:id,code,name_en,name_fr',
                'corridor:id,corridor_code,name_en,name_fr',
                'location:id,location_name,name_en,name_fr,latitude,longitude,country',
                'assignedInstitution:id,institution_name,name_en,name_fr',
            ])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Complaint $c) => [
                'complaint_code' => $c->complaint_code,
                'title' => $c->title,
                'status_group' => $this->statusGroupFor($c->status?->code),
                'status' => $c->status,
                'category' => $c->category,
                'activity_sector' => $c->activitySector,
                'assigned_institution' => $c->assignedInstitution,
                'corridor' => $c->corridor,
                'location' => $c->location,
                'country_origin' => $c->country_origin,
                'country_destination' => $c->country_destination,
                'created_at' => $c->created_at,
                'closed_at' => $c->closed_at,
                'latitude' => $c->location?->latitude ?? $this->countryFallbackLat($c->country_origin),
                'longitude' => $c->location?->longitude ?? $this->countryFallbackLng($c->country_origin),
            ]);

        return response()->json(['reports' => $reports]);
    }

    public function mapPoints(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        $complaintIds = $this->filteredQuery($filters)->pluck('id');

        if ($complaintIds->isEmpty()) {
            return response()->json(['points' => []]);
        }

        $locationCounts = Location::query()
            ->withCount(['complaints as complaints_count' => function (Builder $query) use ($complaintIds) {
                $query->whereIn('complaints.id', $complaintIds);
            }])
            ->having('complaints_count', '>', 0)
            ->get()
            ->map(function (Location $loc) {
                return [
                    'label' => $loc->location_name,
                    'country' => $loc->country,
                    'count' => $loc->complaints_count,
                    'latitude' => (float) ($loc->latitude ?? $this->defaultCoords($loc->location_name)['lat']),
                    'longitude' => (float) ($loc->longitude ?? $this->defaultCoords($loc->location_name)['lng']),
                ];
            });

        if ($locationCounts->isNotEmpty()) {
            return response()->json(['points' => $locationCounts]);
        }

        $countryCounts = $this->filteredQuery($filters)
            ->select('country_origin as country', DB::raw('count(*) as count'))
            ->groupBy('country_origin')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->country,
                'country' => $row->country,
                'count' => $row->count,
                'latitude' => $this->countryFallbackLat($row->country),
                'longitude' => $this->countryFallbackLng($row->country),
            ]);

        return response()->json(['points' => $countryCounts]);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'status_group' => ['nullable', 'string', 'in:received,under_review,resolved,closed'],
            'category_id' => ['nullable', 'integer', 'exists:ntb_categories,id'],
            'country' => ['nullable', 'string', 'max:100'],
            'corridor_id' => ['nullable', 'integer', 'exists:corridors,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'activity_sector_id' => ['nullable', 'integer', 'exists:activity_sectors,id'],
        ]);
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = Complaint::query();

        if (! empty($filters['status_group'])) {
            $codes = self::STATUS_GROUPS[$filters['status_group']];
            $query->whereHas('status', fn ($q) => $q->whereIn('code', $codes));
        }

        if (! empty($filters['category_id'])) {
            $query->where('ntb_category_id', $filters['category_id']);
        }

        if (! empty($filters['country'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('country_origin', $filters['country'])
                    ->orWhere('country_destination', $filters['country']);
            });
        }

        if (! empty($filters['corridor_id'])) {
            $query->where('corridor_id', $filters['corridor_id']);
        }

        if (! empty($filters['year'])) {
            $query->whereYear('created_at', $filters['year']);
        }

        if (! empty($filters['institution_id'])) {
            $institutionId = $filters['institution_id'];
            $query->where(function ($q) use ($institutionId) {
                $q->where('assigned_institution_id', $institutionId)
                    ->orWhereHas('category', fn ($q) => $q->where('responsible_institution_id', $institutionId));
            });
        }

        if (! empty($filters['activity_sector_id'])) {
            $query->where('activity_sector_id', $filters['activity_sector_id']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('complaint_code', 'like', $term)
                    ->orWhere('title', 'like', $term);
            });
        }

        return $query;
    }

    private function hasActiveFilters(array $filters): bool
    {
        return collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();
    }

    private function statusGroupFor(?string $code): string
    {
        if (! $code) {
            return 'received';
        }

        foreach (self::STATUS_GROUPS as $group => $codes) {
            if (in_array($code, $codes, true)) {
                return $group;
            }
        }

        return 'received';
    }

    private function countryFallbackLat(?string $country): float
    {
        return match (strtolower(trim((string) $country))) {
            'ethiopia' => 9.03,
            'djibouti' => 11.58,
            default => 9.5,
        };
    }

    private function countryFallbackLng(?string $country): float
    {
        return match (strtolower(trim((string) $country))) {
            'ethiopia' => 38.74,
            'djibouti' => 43.15,
            default => 42.0,
        };
    }

    private function defaultCoords(string $name): array
    {
        return match (true) {
            str_contains(strtolower($name), 'port') => ['lat' => 11.588, 'lng' => 43.145],
            str_contains(strtolower($name), 'galafi') => ['lat' => 11.716, 'lng' => 41.836],
            str_contains(strtolower($name), 'doraleh') => ['lat' => 11.595, 'lng' => 43.085],
            default => ['lat' => 11.58, 'lng' => 43.15],
        };
    }
}
