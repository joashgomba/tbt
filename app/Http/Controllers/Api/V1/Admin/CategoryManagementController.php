<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\Institution;
use App\Models\NtbCategory;
use App\Models\SlaConfiguration;
use App\Services\Ntb\AuditLogger;
use App\Services\Ntb\Categories\CategoryLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryManagementController extends Controller
{
    private const ICONS = [
        'ti ti-plant-2', 'ti ti-ruler-measure', 'ti ti-building-warehouse',
        'ti ti-file-certificate', 'ti ti-receipt-tax', 'ti ti-truck',
        'ti ti-alert-circle', 'ti ti-scale', 'ti ti-shield-check', 'ti ti-package',
    ];

    public function __construct(
        private readonly CategoryLifecycleService $lifecycle,
        private readonly AuditLogger $audit,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.categories'), 403);

        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        $leaderboard = NtbCategory::query()
            ->with('responsibleInstitution:id,institution_name,institution_code')
            ->withCount([
                'complaints as total_cases_count',
                'complaints as open_cases_count' => fn ($q) => $q->whereNotIn('status_id', $terminalIds),
                'institutions as institutions_count',
            ])
            ->orderByDesc('open_cases_count')
            ->get()
            ->map(function (NtbCategory $cat) use ($terminalIds) {
                $resolved = Complaint::query()
                    ->where('ntb_category_id', $cat->id)
                    ->whereIn('status_id', $terminalIds)
                    ->count();
                $total = $cat->total_cases_count ?: 0;

                return [
                    'category' => $cat,
                    'total_cases' => $total,
                    'open_cases' => $cat->open_cases_count,
                    'institutions' => $cat->institutions_count,
                    'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 1) : 0,
                    'avg_sla_days' => $cat->default_sla_days,
                ];
            });

        return response()->json([
            'summary' => [
                'total' => NtbCategory::query()->count(),
                'active' => NtbCategory::query()->where('is_active', true)->count(),
                'inactive' => NtbCategory::query()->where('is_active', false)->count(),
                'with_parent' => NtbCategory::query()->whereNotNull('parent_id')->count(),
                'open_cases' => Complaint::query()->whereNotIn('status_id', $terminalIds)->count(),
            ],
            'leaderboard' => $leaderboard,
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.categories'), 403);

        return response()->json([
            'institutions' => Institution::query()->where('status', 'active')->orderBy('institution_name')->get(['id', 'institution_name', 'institution_code']),
            'parents' => NtbCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name_en', 'name_fr', 'parent_id']),
            'icons' => self::ICONS,
            'sla_day_options' => [1, 2, 3, 4, 5, 7, 10, 14, 21, 30],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.categories'), 403);

        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        $query = NtbCategory::query()
            ->with(['parent:id,code,name_en', 'responsibleInstitution:id,institution_name,institution_code'])
            ->withCount([
                'complaints as open_cases_count' => fn ($q) => $q->whereNotIn('status_id', $terminalIds),
                'institutions as institutions_count',
                'children as children_count',
            ])
            ->orderBy('sort_order')
            ->orderBy('code');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('name_fr', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('parent_id')) {
            if ($request->input('parent_id') === 'root') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->integer('parent_id'));
            }
        }

        if ($request->filled('institution_id')) {
            $query->where('responsible_institution_id', $request->integer('institution_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.categories'), 403);

        $data = $this->validatedPayload($request);
        $institutionIds = $data['institution_ids'] ?? [];
        unset($data['institution_ids']);

        $category = NtbCategory::query()->create($data);
        if ($institutionIds !== []) {
            $category->institutions()->sync($institutionIds);
        }

        $this->audit->log('category.create', $category, null, $category->toArray(), $request);

        return response()->json(['category' => $category->load(['parent', 'responsibleInstitution', 'institutions'])], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.categories'), 403);

        $category = NtbCategory::query()
            ->with(['parent', 'children', 'responsibleInstitution', 'institutions:id,institution_name,institution_code'])
            ->findOrFail($id);

        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        return response()->json([
            'category' => $category,
            'impact' => $this->lifecycle->impactAssessment($category),
            'sla_configurations' => SlaConfiguration::query()
                ->where('ntb_category_id', $category->id)
                ->orderBy('stage')
                ->get(),
            'recent_cases' => Complaint::query()
                ->where('ntb_category_id', $category->id)
                ->whereNotIn('status_id', $terminalIds)
                ->with(['status:id,code,name_en', 'assignedInstitution:id,institution_name'])
                ->orderByDesc('created_at')
                ->limit(15)
                ->get(['id', 'complaint_code', 'title', 'status_id', 'assigned_institution_id', 'created_at']),
            'case_trend' => Complaint::query()
                ->where('ntb_category_id', $category->id)
                ->where('created_at', '>=', now()->subMonths(6))
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('count(*) as count'))
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.categories'), 403);

        $category = NtbCategory::query()->findOrFail($id);
        $data = $this->validatedPayload($request, $category->id, partial: true);
        $institutionIds = $data['institution_ids'] ?? null;
        unset($data['institution_ids']);

        $this->assertValidParent($category, $data['parent_id'] ?? $category->parent_id);

        $old = $category->only(array_keys($data));
        $category->update($data);

        if ($institutionIds !== null) {
            $category->institutions()->sync($institutionIds);
        }

        $this->audit->log('category.update', $category, $old, $category->fresh()->only(array_keys($old)), $request);

        return response()->json(['category' => $category->fresh()->load(['parent', 'responsibleInstitution', 'institutions'])]);
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.categories'), 403);

        $category = NtbCategory::query()->findOrFail($id);

        return response()->json(['category' => $this->lifecycle->activate($request, $category)]);
    }

    public function deactivate(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.categories'), 403);

        $category = NtbCategory::query()->findOrFail($id);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return response()->json([
            'category' => $this->lifecycle->deactivate($request, $category, $data['reason'] ?? null),
        ]);
    }

    public function auditLogs(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.categories'), 403);

        NtbCategory::query()->findOrFail($id);

        $logs = AuditLog::query()
            ->where('entity_type', NtbCategory::class)
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
            'code' => [$partial ? 'sometimes' : 'required', 'string', 'max:20', Rule::unique('ntb_categories', 'code')->ignore($ignoreId)],
            'name_en' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'name_fr' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_fr' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'parent_id' => ['nullable', 'exists:ntb_categories,id'],
            'default_sla_days' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1', 'max:90'],
            'responsible_institution_id' => ['nullable', 'exists:institutions,id'],
            'is_active' => ['sometimes', 'boolean'],
            'institution_ids' => ['nullable', 'array'],
            'institution_ids.*' => ['integer', 'exists:institutions,id'],
        ];

        return $request->validate($rules);
    }

    private function assertValidParent(NtbCategory $category, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        abort_if($parentId === $category->id, 422, 'A category cannot be its own parent.');

        $ancestorId = $parentId;
        while ($ancestorId) {
            abort_if($ancestorId === $category->id, 422, 'Circular parent hierarchy is not allowed.');
            $ancestorId = NtbCategory::query()->whereKey($ancestorId)->value('parent_id');
        }
    }
}
