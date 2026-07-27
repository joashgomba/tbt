<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\Corridor;
use App\Models\Institution;
use App\Models\InstitutionUnit;
use App\Models\NtbCategory;
use App\Models\SlaTracking;
use App\Models\User;
use App\Services\Ntb\AuditLogger;
use App\Services\Ntb\Institutions\InstitutionLifecycleService;
use App\Services\Ntb\Institutions\InstitutionScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InstitutionManagementController extends Controller
{
    private const TYPES = [
        'lead_ministry', 'national_governance', 'customs', 'port_logistics', 'sps',
        'standards', 'transport', 'permit_licensing', 'private_association',
        'economic_operator', 'womens_association', 'telecom', 'development_partner',
        'regional_body', 'technical_service', 'ministry', 'customs', 'port', 'private', 'sps',
    ];

    private const STATUSES = ['active', 'inactive', 'suspended', 'archived'];

    public function __construct(
        private readonly InstitutionScopeService $scope,
        private readonly InstitutionLifecycleService $lifecycle,
        private readonly AuditLogger $audit,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        abort_unless($this->scope->canManage($request) || $request->user()?->institution_id, 403);

        $query = $this->scope->scopedQuery($request);
        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        $institutionIds = (clone $query)->pluck('id');

        $openCases = Complaint::query()
            ->whereIn('assigned_institution_id', $institutionIds)
            ->whereNotIn('status_id', $terminalIds)
            ->count();

        $leaderboard = (clone $query)
            ->withCount([
                'users as active_users_count' => fn ($q) => $q->where('status', 'active'),
                'complaints as open_cases_count' => fn ($q) => $q->whereNotIn('status_id', $terminalIds),
            ])
            ->get()
            ->map(function (Institution $inst) use ($terminalIds) {
                $slaBreached = SlaTracking::query()
                    ->where('institution_id', $inst->id)
                    ->where('is_breached', true)
                    ->count();
                $slaTotal = SlaTracking::query()
                    ->where('institution_id', $inst->id)
                    ->count();

                return [
                    'institution' => $inst,
                    'active_users' => $inst->active_users_count,
                    'open_cases' => $inst->open_cases_count,
                    'focal_points' => User::query()
                        ->where('institution_id', $inst->id)
                        ->where('is_focal_point', true)
                        ->where('status', 'active')
                        ->count(),
                    'sla_pct' => $slaTotal > 0 ? (int) round((($slaTotal - $slaBreached) / $slaTotal) * 100) : 100,
                    'resolution_rate' => $this->resolutionRate($inst->id, $terminalIds),
                ];
            })
            ->sortByDesc('open_cases')
            ->values()
            ->take(15);

        return response()->json([
            'summary' => [
                'total' => (clone $query)->count(),
                'active' => (clone $query)->where('status', 'active')->count(),
                'suspended' => (clone $query)->where('status', 'suspended')->count(),
                'inactive' => (clone $query)->whereIn('status', ['inactive', 'archived'])->count(),
                'open_cases' => $openCases,
                'total_users' => User::query()->whereIn('institution_id', $institutionIds)->where('status', 'active')->count(),
            ],
            'leaderboard' => $leaderboard,
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        abort_unless($this->scope->canManage($request) || $request->user()?->institution_id, 403);

        return response()->json([
            'types' => array_values(array_unique(self::TYPES)),
            'statuses' => self::STATUSES,
            'countries' => ['DJ', 'ET', 'KE', 'SO'],
            'corridors' => Corridor::query()->where('active', true)->orderBy('corridor_code')->get(['id', 'corridor_code', 'name_en', 'name_fr']),
            'categories' => NtbCategory::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name_en', 'name_fr']),
            'parents' => $this->scope->scopedQuery($request)->where('status', 'active')->orderBy('institution_name')->get(['id', 'institution_name', 'institution_code']),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->scope->canManage($request) || $request->user()?->institution_id, 403);

        $query = $this->scope->scopedQuery($request)
            ->withCount([
                'users as active_users_count' => fn ($q) => $q->where('status', 'active'),
                'units as units_count',
            ])
            ->with(['corridors:id,corridor_code', 'ntbCategories:id,code'])
            ->orderBy('institution_name');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('institution_name', 'like', "%{$search}%")
                    ->orWhere('institution_code', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('institution_type')) {
            $query->where('institution_type', $request->string('institution_type'));
        }

        if ($request->filled('country_code')) {
            $query->where('country_code', $request->string('country_code'));
        }

        if ($request->filled('corridor_id')) {
            $query->whereHas('corridors', fn ($q) => $q->where('corridors.id', $request->integer('corridor_id')));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->scope->canManage($request), 403);

        $data = $this->validatedPayload($request);
        $corridorIds = $data['corridor_ids'] ?? [];
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['corridor_ids'], $data['category_ids']);

        $institution = Institution::query()->create($data);
        $institution->corridors()->sync($corridorIds);
        $institution->ntbCategories()->sync($categoryIds);

        $this->audit->log('institution.create', $institution, null, $institution->toArray(), $request);

        return response()->json(['institution' => $institution->load(['corridors', 'ntbCategories'])], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $institution = $this->scopedInstitution($request, $id);
        abort_unless($this->scope->canViewInstitution($request, $institution), 403);

        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        return response()->json([
            'institution' => $institution->load(['parent', 'corridors', 'ntbCategories', 'units']),
            'impact' => $this->lifecycle->impactAssessment($institution),
            'focal_points' => User::query()
                ->where('institution_id', $institution->id)
                ->where('is_focal_point', true)
                ->with('roles:id,name')
                ->get(['id', 'name', 'full_name', 'email', 'status']),
            'administrators' => User::query()
                ->where('institution_id', $institution->id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'institutional_administrator'))
                ->get(['id', 'name', 'full_name', 'email', 'status']),
            'open_cases' => Complaint::query()
                ->where('assigned_institution_id', $institution->id)
                ->whereNotIn('status_id', $terminalIds)
                ->with(['status:id,code,name_en', 'category:id,code'])
                ->limit(20)
                ->get(['id', 'complaint_code', 'title', 'status_id', 'ntb_category_id', 'created_at']),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $institution = $this->scopedInstitution($request, $id);
        abort_unless($this->scope->canModifyInstitution($request, $institution) || $this->scope->canManage($request), 403);

        $data = $this->validatedPayload($request, $institution->id, partial: true);
        $corridorIds = $data['corridor_ids'] ?? null;
        $categoryIds = $data['category_ids'] ?? null;
        unset($data['corridor_ids'], $data['category_ids']);

        $old = $institution->only(array_keys($data));
        $institution->update($data);

        if ($corridorIds !== null) {
            $institution->corridors()->sync($corridorIds);
        }
        if ($categoryIds !== null) {
            $institution->ntbCategories()->sync($categoryIds);
        }

        $this->audit->log('institution.update', $institution, $old, $institution->fresh()->only(array_keys($old)), $request);

        return response()->json(['institution' => $institution->fresh()->load(['corridors', 'ntbCategories', 'units'])]);
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        $institution = $this->scopedInstitution($request, $id);

        return response()->json([
            'institution' => $this->lifecycle->activate($request, $institution, $request->string('reason')->toString() ?: null),
        ]);
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $institution = $this->scopedInstitution($request, $id);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json([
            'institution' => $this->lifecycle->suspend($request, $institution, $data['reason']),
        ]);
    }

    public function deactivate(Request $request, int $id): JsonResponse
    {
        $institution = $this->scopedInstitution($request, $id);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json($this->lifecycle->deactivate($request, $institution, $data['reason']));
    }

    public function users(Request $request, int $id): JsonResponse
    {
        $institution = $this->scopedInstitution($request, $id);
        abort_unless($this->scope->canViewInstitution($request, $institution), 403);

        $users = User::query()
            ->where('institution_id', $institution->id)
            ->with(['roles:id,name', 'unit:id,name_en,unit_code'])
            ->orderBy('full_name')
            ->get();

        return response()->json(['users' => $users]);
    }

    public function units(Request $request, int $id): JsonResponse
    {
        $institution = $this->scopedInstitution($request, $id);
        abort_unless($this->scope->canViewInstitution($request, $institution), 403);

        if ($request->isMethod('post')) {
            abort_unless($this->scope->canModifyInstitution($request, $institution) || $this->scope->canManage($request), 403);

            $data = $request->validate([
                'unit_code' => ['required', 'string', 'max:50'],
                'name_en' => ['required', 'string', 'max:255'],
                'name_fr' => ['required', 'string', 'max:255'],
                'parent_id' => ['nullable', 'exists:institution_units,id'],
                'manager_id' => ['nullable', 'exists:users,id'],
            ]);

            $unit = InstitutionUnit::query()->create([...$data, 'institution_id' => $institution->id]);
            $this->audit->log('institution.unit.create', $unit, null, $unit->toArray(), $request);

            return response()->json(['unit' => $unit], 201);
        }

        return response()->json([
            'units' => $institution->units()->with(['manager:id,name', 'parent:id,unit_code,name_en'])->orderBy('name_en')->get(),
        ]);
    }

    public function updateUnit(Request $request, int $unitId): JsonResponse
    {
        $unit = InstitutionUnit::query()->findOrFail($unitId);
        $institution = $unit->institution;
        abort_unless($this->scope->canModifyInstitution($request, $institution) || $this->scope->canManage($request), 403);

        $data = $request->validate([
            'name_en' => ['sometimes', 'string', 'max:255'],
            'name_fr' => ['sometimes', 'string', 'max:255'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (($data['is_active'] ?? true) === false) {
            $activeUsers = User::query()->where('unit_id', $unit->id)->where('status', 'active')->count();
            abort_if($activeUsers > 0, 422, 'Unit has active users — reassign them first.');
        }

        $unit->update($data);
        $this->audit->log('institution.unit.update', $unit, null, $data, $request);

        return response()->json(['unit' => $unit->fresh()]);
    }

    public function auditLogs(Request $request, int $id): JsonResponse
    {
        $institution = $this->scopedInstitution($request, $id);
        abort_unless($this->scope->canViewInstitution($request, $institution), 403);

        $logs = AuditLog::query()
            ->where('entity_type', Institution::class)
            ->where('entity_id', $institution->id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['logs' => $logs]);
    }

    private function scopedInstitution(Request $request, int $id): Institution
    {
        return $this->scope->scopedQuery($request)->findOrFail($id);
    }

    private function validatedPayload(Request $request, ?int $ignoreId = null, bool $partial = false): array
    {
        $rules = [
            'institution_name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'institution_code' => [$partial ? 'sometimes' : 'required', 'string', 'max:30', Rule::unique('institutions', 'institution_code')->ignore($ignoreId)],
            'name_en' => ['nullable', 'string', 'max:255'],
            'name_fr' => ['nullable', 'string', 'max:255'],
            'institution_type' => [$partial ? 'sometimes' : 'required', 'string', 'max:80'],
            'country_code' => [$partial ? 'sometimes' : 'required', 'string', 'size:2'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'mandate' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:institutions,id'],
            'integration_code' => ['nullable', 'string', 'max:80'],
            'escalation_contacts' => ['nullable', 'array'],
            'notification_channels' => ['nullable', 'array'],
            'operating_hours' => ['nullable', 'array'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'corridor_ids' => ['nullable', 'array'],
            'corridor_ids.*' => ['integer', 'exists:corridors,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:ntb_categories,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];

        $data = $request->validate($rules);

        if (! $partial && empty($data['name_en'])) {
            $data['name_en'] = $data['institution_name'];
        }
        if (! $partial && empty($data['name_fr'])) {
            $data['name_fr'] = $data['institution_name'];
        }

        return $data;
    }

    private function resolutionRate(int $institutionId, $terminalIds): float
    {
        $total = Complaint::query()->where('assigned_institution_id', $institutionId)->count();
        if ($total === 0) {
            return 0;
        }

        $resolved = Complaint::query()
            ->where('assigned_institution_id', $institutionId)
            ->whereIn('status_id', $terminalIds)
            ->count();

        return round(($resolved / $total) * 100, 1);
    }
}
