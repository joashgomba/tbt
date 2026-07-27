<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\Location;
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

    public function stats(): JsonResponse
    {
        $total = Complaint::query()->count();

        $resolvedOrClosed = Complaint::query()
            ->whereHas('status', fn ($q) => $q->whereIn('code', ['resolved', 'closed', 'archived']))
            ->count();

        $avgResolutionHours = Complaint::query()
            ->whereNotNull('closed_at')
            ->whereHas('status', fn ($q) => $q->whereIn('code', ['resolved', 'closed', 'archived']))
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, closed_at)) as avg_hours')
            ->value('avg_hours');

        $byStatusGroup = [];
        foreach (self::STATUS_GROUPS as $group => $codes) {
            $byStatusGroup[$group] = Complaint::query()
                ->whereHas('status', fn ($q) => $q->whereIn('code', $codes))
                ->count();
        }

        $byCategory = Complaint::query()
            ->select('ntb_category_id', DB::raw('count(*) as count'))
            ->groupBy('ntb_category_id')
            ->with('category:id,code,name_en,name_fr')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'count' => $row->count,
            ]);

        $byCountry = Complaint::query()
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
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status_group' => ['nullable', 'string', 'in:received,under_review,resolved,closed'],
            'category_id' => ['nullable', 'integer', 'exists:ntb_categories,id'],
            'country' => ['nullable', 'string', 'max:100'],
            'corridor_id' => ['nullable', 'integer', 'exists:corridors,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Complaint::query()
            ->with(['category:id,code,name_en,name_fr', 'status:id,code,name_en,name_fr', 'corridor:id,corridor_code,name_en,name_fr', 'location:id,location_name,name_en,name_fr,latitude,longitude,country'])
            ->latest();

        if (! empty($data['status_group'])) {
            $codes = self::STATUS_GROUPS[$data['status_group']];
            $query->whereHas('status', fn ($q) => $q->whereIn('code', $codes));
        }

        if (! empty($data['category_id'])) {
            $query->where('ntb_category_id', $data['category_id']);
        }

        if (! empty($data['country'])) {
            $query->where(function ($q) use ($data) {
                $q->where('country_origin', $data['country'])
                    ->orWhere('country_destination', $data['country']);
            });
        }

        if (! empty($data['corridor_id'])) {
            $query->where('corridor_id', $data['corridor_id']);
        }

        if (! empty($data['search'])) {
            $term = '%'.$data['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('complaint_code', 'like', $term)
                    ->orWhere('title', 'like', $term);
            });
        }

        $reports = $query->limit(100)->get()->map(fn (Complaint $c) => [
            'complaint_code' => $c->complaint_code,
            'title' => $c->title,
            'status_group' => $this->statusGroupFor($c->status?->code),
            'status' => $c->status,
            'category' => $c->category,
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

    public function mapPoints(): JsonResponse
    {
        $locationCounts = Location::query()
            ->withCount('complaints')
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

        $countryCounts = Complaint::query()
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

        return response()->json([
            'points' => $locationCounts->isNotEmpty() ? $locationCounts : $countryCounts,
        ]);
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
