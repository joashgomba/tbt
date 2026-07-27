<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\Corridor;
use App\Models\Institution;
use App\Models\Location;
use App\Services\Ntb\AuditLogger;
use App\Services\Ntb\Corridors\CorridorLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CorridorManagementController extends Controller
{
    private const MODES = ['road', 'rail', 'sea', 'multimodal', 'air'];

    private const ICONS = [
        'ti ti-route', 'ti ti-truck', 'ti ti-train', 'ti ti-ship', 'ti ti-plane',
        'ti ti-map-pin', 'ti ti-arrows-left-right', 'ti ti-world',
    ];

    private const COUNTRIES = [
        'Ethiopia', 'Djibouti', 'Kenya', 'Somalia', 'Sudan', 'Eritrea', 'South Sudan',
    ];

    public function __construct(
        private readonly CorridorLifecycleService $lifecycle,
        private readonly AuditLogger $audit,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.corridors'), 403);

        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        $leaderboard = Corridor::query()
            ->withCount([
                'complaints as total_cases_count',
                'complaints as open_cases_count' => fn ($q) => $q->whereNotIn('status_id', $terminalIds),
                'locations as locations_count',
                'institutions as institutions_count',
            ])
            ->orderByDesc('open_cases_count')
            ->get()
            ->map(function (Corridor $corridor) use ($terminalIds) {
                $resolved = Complaint::query()
                    ->where('corridor_id', $corridor->id)
                    ->whereIn('status_id', $terminalIds)
                    ->count();
                $total = $corridor->total_cases_count ?: 0;

                return [
                    'corridor' => $corridor,
                    'total_cases' => $total,
                    'open_cases' => $corridor->open_cases_count,
                    'locations' => $corridor->locations_count,
                    'institutions' => $corridor->institutions_count,
                    'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 1) : 0,
                ];
            });

        return response()->json([
            'summary' => [
                'total' => Corridor::query()->count(),
                'active' => Corridor::query()->where('active', true)->count(),
                'inactive' => Corridor::query()->where('active', false)->count(),
                'total_locations' => Location::query()->count(),
                'border_posts' => Location::query()->where('border_post', true)->count(),
                'open_cases' => Complaint::query()->whereNotIn('status_id', $terminalIds)->count(),
            ],
            'leaderboard' => $leaderboard,
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.corridors'), 403);

        return response()->json([
            'countries' => self::COUNTRIES,
            'modes' => self::MODES,
            'icons' => self::ICONS,
            'institutions' => Institution::query()->where('status', 'active')->orderBy('institution_name')->get(['id', 'institution_name', 'institution_code']),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.corridors'), 403);

        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        $query = Corridor::query()
            ->withCount([
                'complaints as open_cases_count' => fn ($q) => $q->whereNotIn('status_id', $terminalIds),
                'locations as locations_count',
                'institutions as institutions_count',
            ])
            ->orderBy('sort_order')
            ->orderBy('corridor_code');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('corridor_code', 'like', "%{$search}%")
                    ->orWhere('corridor_name', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('origin_country', 'like', "%{$search}%")
                    ->orWhere('destination_country', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('origin_country')) {
            $query->where('origin_country', $request->string('origin_country'));
        }

        if ($request->filled('destination_country')) {
            $query->where('destination_country', $request->string('destination_country'));
        }

        if ($request->filled('primary_mode')) {
            $query->where('primary_mode', $request->string('primary_mode'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.corridors'), 403);

        $data = $this->validatedPayload($request);
        $institutionIds = $data['institution_ids'] ?? [];
        unset($data['institution_ids']);

        $corridor = Corridor::query()->create($data);
        if ($institutionIds !== []) {
            $corridor->institutions()->sync($institutionIds);
        }

        $this->audit->log('corridor.create', $corridor, null, $corridor->toArray(), $request);

        return response()->json(['corridor' => $corridor->load('institutions')], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.corridors'), 403);

        $corridor = Corridor::query()
            ->with(['locations', 'institutions:id,institution_name,institution_code,country_code'])
            ->findOrFail($id);

        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        return response()->json([
            'corridor' => $corridor,
            'impact' => $this->lifecycle->impactAssessment($corridor),
            'recent_cases' => Complaint::query()
                ->where('corridor_id', $corridor->id)
                ->whereNotIn('status_id', $terminalIds)
                ->with(['status:id,code,name_en', 'category:id,code', 'location:id,location_name'])
                ->orderByDesc('created_at')
                ->limit(15)
                ->get(['id', 'complaint_code', 'title', 'status_id', 'ntb_category_id', 'location_id', 'created_at']),
            'case_trend' => Complaint::query()
                ->where('corridor_id', $corridor->id)
                ->where('created_at', '>=', now()->subMonths(6))
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('count(*) as count'))
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
            'cases_by_category' => Complaint::query()
                ->where('corridor_id', $corridor->id)
                ->join('ntb_categories', 'complaints.ntb_category_id', '=', 'ntb_categories.id')
                ->select('ntb_categories.code', 'ntb_categories.name_en', DB::raw('count(*) as count'))
                ->groupBy('ntb_categories.id', 'ntb_categories.code', 'ntb_categories.name_en')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'cases_by_location' => Complaint::query()
                ->join('locations', 'complaints.location_id', '=', 'locations.id')
                ->where('complaints.corridor_id', $corridor->id)
                ->whereNotNull('complaints.location_id')
                ->select('locations.location_name', DB::raw('count(*) as count'))
                ->groupBy('locations.id', 'locations.location_name')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.corridors'), 403);

        $corridor = Corridor::query()->findOrFail($id);
        $data = $this->validatedPayload($request, $corridor->id, partial: true);
        $institutionIds = $data['institution_ids'] ?? null;
        unset($data['institution_ids']);

        $old = $corridor->only(array_keys($data));
        $corridor->update($data);

        if ($institutionIds !== null) {
            $corridor->institutions()->sync($institutionIds);
        }

        $this->audit->log('corridor.update', $corridor, $old, $corridor->fresh()->only(array_keys($old)), $request);

        return response()->json(['corridor' => $corridor->fresh()->load(['locations', 'institutions'])]);
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.corridors'), 403);

        return response()->json([
            'corridor' => $this->lifecycle->activate($request, Corridor::query()->findOrFail($id)),
        ]);
    }

    public function deactivate(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.corridors'), 403);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return response()->json([
            'corridor' => $this->lifecycle->deactivate($request, Corridor::query()->findOrFail($id), $data['reason'] ?? null),
        ]);
    }

    public function locations(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.corridors'), 403);

        $corridor = Corridor::query()->findOrFail($id);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'location_name' => ['required', 'string', 'max:255'],
                'name_en' => ['nullable', 'string', 'max:255'],
                'name_fr' => ['nullable', 'string', 'max:255'],
                'country' => ['required', 'string', 'max:100'],
                'region' => ['nullable', 'string', 'max:100'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'border_post' => ['boolean'],
            ]);

            if (empty($data['name_en'])) {
                $data['name_en'] = $data['location_name'];
            }
            if (empty($data['name_fr'])) {
                $data['name_fr'] = $data['location_name'];
            }

            $location = Location::query()->create([...$data, 'corridor_id' => $corridor->id]);
            $this->audit->log('corridor.location.create', $location, null, $location->toArray(), $request);

            return response()->json(['location' => $location], 201);
        }

        return response()->json(['locations' => $corridor->locations()->orderBy('location_name')->get()]);
    }

    public function updateLocation(Request $request, int $locationId): JsonResponse
    {
        abort_unless($request->user()?->can('admin.corridors'), 403);

        $location = Location::query()->findOrFail($locationId);

        $data = $request->validate([
            'location_name' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'name_fr' => ['nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'border_post' => ['sometimes', 'boolean'],
            'corridor_id' => ['nullable', 'exists:corridors,id'],
        ]);

        $old = $location->only(array_keys($data));
        $location->update($data);
        $this->audit->log('corridor.location.update', $location, $old, $data, $request);

        return response()->json(['location' => $location->fresh()]);
    }

    public function deleteLocation(Request $request, int $locationId): JsonResponse
    {
        abort_unless($request->user()?->can('admin.corridors'), 403);

        $location = Location::query()->findOrFail($locationId);
        $openCases = Complaint::query()
            ->where('location_id', $location->id)
            ->whereNotIn('status_id', ComplaintStatus::query()->where('is_terminal', true)->pluck('id'))
            ->count();

        abort_if($openCases > 0, 422, "Cannot remove location: {$openCases} open case(s) reference it.");

        $this->audit->log('corridor.location.delete', $location, $location->toArray(), null, $request);
        $location->delete();

        return response()->json(['deleted' => true]);
    }

    public function auditLogs(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.corridors'), 403);

        Corridor::query()->findOrFail($id);

        $logs = AuditLog::query()
            ->where('entity_type', Corridor::class)
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
            'corridor_code' => [$partial ? 'sometimes' : 'required', 'string', 'max:50', Rule::unique('corridors', 'corridor_code')->ignore($ignoreId)],
            'corridor_name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'name_fr' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_fr' => ['nullable', 'string'],
            'origin_country' => [$partial ? 'sometimes' : 'required', 'string', 'max:100'],
            'destination_country' => [$partial ? 'sometimes' : 'required', 'string', 'max:100'],
            'primary_mode' => ['nullable', Rule::in(self::MODES)],
            'distance_km' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'transit_time_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'icon' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
            'institution_ids' => ['nullable', 'array'],
            'institution_ids.*' => ['integer', 'exists:institutions,id'],
        ];

        $data = $request->validate($rules);

        if (! $partial && empty($data['name_en'])) {
            $data['name_en'] = $data['corridor_name'];
        }
        if (! $partial && empty($data['name_fr'])) {
            $data['name_fr'] = $data['corridor_name'];
        }

        return $data;
    }
}
