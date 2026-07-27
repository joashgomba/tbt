<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\Corridor;
use App\Models\Location;
use App\Services\Ntb\AuditLogger;
use App\Services\Ntb\Locations\LocationLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LocationManagementController extends Controller
{
    private const TYPES = [
        'port', 'border_post', 'checkpoint', 'terminal', 'warehouse', 'city', 'inland_depot', 'other',
    ];

    private const ICONS = [
        'ti ti-map-pin', 'ti ti-building-warehouse', 'ti ti-anchor', 'ti ti-building-fortress',
        'ti ti-truck', 'ti ti-building', 'ti ti-flag', 'ti ti-road',
    ];

    private const COUNTRIES = [
        'Djibouti', 'Ethiopia', 'Kenya', 'Somalia', 'Sudan', 'Eritrea', 'South Sudan',
    ];

    public function __construct(
        private readonly LocationLifecycleService $lifecycle,
        private readonly AuditLogger $audit,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.locations'), 403);

        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        $leaderboard = Location::query()
            ->with('corridor:id,corridor_code,corridor_name')
            ->withCount([
                'complaints as total_cases_count',
                'complaints as open_cases_count' => fn ($q) => $q->whereNotIn('status_id', $terminalIds),
            ])
            ->orderByDesc('open_cases_count')
            ->limit(20)
            ->get()
            ->map(function (Location $loc) use ($terminalIds) {
                $resolved = Complaint::query()
                    ->where('location_id', $loc->id)
                    ->whereIn('status_id', $terminalIds)
                    ->count();
                $total = $loc->total_cases_count ?: 0;

                return [
                    'location' => $loc,
                    'total_cases' => $total,
                    'open_cases' => $loc->open_cases_count,
                    'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 1) : 0,
                ];
            });

        return response()->json([
            'summary' => [
                'total' => Location::query()->count(),
                'active' => Location::query()->where('is_active', true)->count(),
                'inactive' => Location::query()->where('is_active', false)->count(),
                'border_posts' => Location::query()->where('border_post', true)->where('is_active', true)->count(),
                'with_coordinates' => Location::query()
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->count(),
                'unassigned_corridor' => Location::query()->whereNull('corridor_id')->count(),
                'open_cases' => Complaint::query()->whereNotNull('location_id')->whereNotIn('status_id', $terminalIds)->count(),
            ],
            'leaderboard' => $leaderboard,
            'by_type' => Location::query()
                ->select('location_type', DB::raw('count(*) as count'))
                ->groupBy('location_type')
                ->orderByDesc('count')
                ->get(),
            'by_country' => Location::query()
                ->select('country', DB::raw('count(*) as count'))
                ->groupBy('country')
                ->orderByDesc('count')
                ->get(),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.locations'), 403);

        return response()->json([
            'corridors' => Corridor::query()->where('active', true)->orderBy('sort_order')->orderBy('corridor_code')->get(['id', 'corridor_code', 'corridor_name', 'name_en']),
            'countries' => self::COUNTRIES,
            'location_types' => self::TYPES,
            'icons' => self::ICONS,
        ]);
    }

    public function mapData(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.locations'), 403);

        $query = Location::query()
            ->with('corridor:id,corridor_code')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($request->filled('corridor_id')) {
            $query->where('corridor_id', $request->integer('corridor_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $locations = $query->get(['id', 'location_code', 'location_name', 'name_en', 'latitude', 'longitude', 'location_type', 'border_post', 'country', 'corridor_id', 'is_active']);

        return response()->json(['locations' => $locations]);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.locations'), 403);

        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        $query = Location::query()
            ->with('corridor:id,corridor_code,corridor_name,origin_country,destination_country')
            ->withCount([
                'complaints as open_cases_count' => fn ($q) => $q->whereNotIn('status_id', $terminalIds),
            ])
            ->orderBy('sort_order')
            ->orderBy('location_name');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('location_name', 'like', "%{$search}%")
                    ->orWhere('location_code', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('corridor_id')) {
            if ($request->input('corridor_id') === 'none') {
                $query->whereNull('corridor_id');
            } else {
                $query->where('corridor_id', $request->integer('corridor_id'));
            }
        }

        if ($request->filled('country')) {
            $query->where('country', $request->string('country'));
        }

        if ($request->filled('location_type')) {
            $query->where('location_type', $request->string('location_type'));
        }

        if ($request->filled('border_post')) {
            $query->where('border_post', filter_var($request->input('border_post'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('has_coordinates')) {
            $has = filter_var($request->input('has_coordinates'), FILTER_VALIDATE_BOOLEAN);
            if ($has) {
                $query->whereNotNull('latitude')->whereNotNull('longitude');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('latitude')->orWhereNull('longitude');
                });
            }
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.locations'), 403);

        $data = $this->validatedPayload($request);
        $location = Location::query()->create($data);

        $this->audit->log('location.create', $location, null, $location->toArray(), $request);

        return response()->json(['location' => $location->load('corridor')], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.locations'), 403);

        $location = Location::query()->with('corridor')->findOrFail($id);
        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        return response()->json([
            'location' => $location,
            'impact' => $this->lifecycle->impactAssessment($location),
            'recent_cases' => Complaint::query()
                ->where('location_id', $location->id)
                ->whereNotIn('status_id', $terminalIds)
                ->with(['status:id,code,name_en', 'category:id,code', 'corridor:id,corridor_code'])
                ->orderByDesc('created_at')
                ->limit(15)
                ->get(['id', 'complaint_code', 'title', 'status_id', 'ntb_category_id', 'corridor_id', 'created_at']),
            'case_trend' => Complaint::query()
                ->where('location_id', $location->id)
                ->where('created_at', '>=', now()->subMonths(6))
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('count(*) as count'))
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
            'cases_by_category' => Complaint::query()
                ->where('location_id', $location->id)
                ->join('ntb_categories', 'complaints.ntb_category_id', '=', 'ntb_categories.id')
                ->select('ntb_categories.code', 'ntb_categories.name_en', DB::raw('count(*) as count'))
                ->groupBy('ntb_categories.id', 'ntb_categories.code', 'ntb_categories.name_en')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.locations'), 403);

        $location = Location::query()->findOrFail($id);
        $data = $this->validatedPayload($request, $location->id, partial: true);

        $old = $location->only(array_keys($data));
        $location->update($data);

        $this->audit->log('location.update', $location, $old, $location->fresh()->only(array_keys($old)), $request);

        return response()->json(['location' => $location->fresh()->load('corridor')]);
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.locations'), 403);

        return response()->json([
            'location' => $this->lifecycle->activate($request, Location::query()->findOrFail($id)),
        ]);
    }

    public function deactivate(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.locations'), 403);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return response()->json([
            'location' => $this->lifecycle->deactivate($request, Location::query()->findOrFail($id), $data['reason'] ?? null),
        ]);
    }

    public function auditLogs(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.locations'), 403);

        Location::query()->findOrFail($id);

        $logs = AuditLog::query()
            ->where('entity_type', Location::class)
            ->where('entity_id', $id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['logs' => $logs]);
    }

    private function validatedPayload(Request $request, ?int $ignoreId = null, bool $partial = false): array
    {
        $rules = [
            'location_code' => ['nullable', 'string', 'max:50', Rule::unique('locations', 'location_code')->ignore($ignoreId)],
            'location_name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'name_fr' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_fr' => ['nullable', 'string'],
            'location_type' => ['nullable', Rule::in(self::TYPES)],
            'icon' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'country' => [$partial ? 'sometimes' : 'required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'border_post' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'corridor_id' => ['nullable', 'exists:corridors,id'],
        ];

        $data = $request->validate($rules);

        if (! $partial && empty($data['name_en'])) {
            $data['name_en'] = $data['location_name'];
        }
        if (! $partial && empty($data['name_fr'])) {
            $data['name_fr'] = $data['location_name'];
        }

        if (isset($data['location_code'])) {
            $data['location_code'] = $data['location_code'] ? strtoupper($data['location_code']) : null;
        }

        if (($data['location_type'] ?? null) === 'border_post') {
            $data['border_post'] = true;
        }

        return $data;
    }
}
