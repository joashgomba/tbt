<?php

namespace App\Services\Ntb\Reports;

use App\Models\CaseHistory;
use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\Corridor;
use App\Models\Escalation;
use App\Models\Institution;
use App\Models\Location;
use App\Models\NtbCategory;
use App\Models\ReportKpiDefinition;
use App\Models\SlaTracking;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportAnalyticsService
{
    public function __construct(
        private readonly ReportScopeService $scope,
    ) {}

    public function buildDashboard(Request $request, array $filters = []): array
    {
        $cutoff = now();
        $baseQuery = $this->scope->scopedComplaintsQuery($request);
        $query = $this->scope->applyFilters(clone $baseQuery, $filters, null);
        $complaintIds = (clone $query)->pluck('id');

        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');
        $closedIds = ComplaintStatus::query()->whereIn('code', ['closed', 'archived', 'rejected'])->pluck('id');
        $resolvedIds = ComplaintStatus::query()->whereIn('code', ['resolved', 'closed', 'archived'])->pluck('id');
        $validationIds = ComplaintStatus::query()->whereIn('code', ['submitted', 'under_validation'])->pluck('id');

        $total = $complaintIds->count();
        $open = (clone $query)->whereNotIn('status_id', $terminalIds)->count();
        $closed = (clone $query)->whereIn('status_id', $closedIds)->count();
        $validationQueue = (clone $query)->whereIn('status_id', $validationIds)->count();

        $kpis = $this->calculateKpis($query, $complaintIds, $resolvedIds, $closedIds, $terminalIds);

        $comparison = $this->comparisonPeriod($request, $filters, $total, $kpis);

        $leaderboard = $this->buildLeaderboard($query, $complaintIds, $resolvedIds, $terminalIds);

        $byMonth = (clone $query)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $year = (int) ($filters['year'] ?? now()->year);
        $trendYear = $this->yearTrend($query, $year);

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

        $byGender = (clone $query)
            ->selectRaw("CASE WHEN gender IS NULL OR gender IN ('', 'NA') THEN 'not_stated' ELSE gender END as gender_key, COUNT(*) as total")
            ->groupBy('gender_key')
            ->get();

        $byChannel = (clone $query)
            ->select('submission_channel', DB::raw('count(*) as total'))
            ->groupBy('submission_channel')
            ->get();

        $byPriority = (clone $query)
            ->select('priority', DB::raw('count(*) as total'))
            ->groupBy('priority')
            ->get();

        $ageing = $this->ageingBands($query, $terminalIds);
        $mapPoints = $this->mapPoints($complaintIds);
        $qualityWarnings = $this->dataQualityWarnings($query);
        $recurrence = $this->recurrenceAnalysis($query);
        $escalations = $this->escalationSummary($complaintIds);
        $stageTimes = $this->stageTimeAnalysis($complaintIds);

        return [
            'cutoff_at' => $cutoff->toIso8601String(),
            'timezone' => config('app.timezone', 'Africa/Djibouti'),
            'summary' => array_merge($kpis, [
                'total' => $total,
                'open' => $open,
                'closed' => $closed,
                'validation_queue' => $validationQueue,
            ]),
            'comparison' => $comparison,
            'leaderboard' => $leaderboard,
            'by_month' => $byMonth,
            'trend_year' => $trendYear,
            'by_status' => $byStatus,
            'by_category' => $byCategory,
            'by_corridor' => $byCorridor,
            'by_gender' => $byGender,
            'by_channel' => $byChannel,
            'by_priority' => $byPriority,
            'ageing' => $ageing,
            'map_points' => $mapPoints,
            'quality_warnings' => $qualityWarnings,
            'recurrence' => $recurrence,
            'escalations' => $escalations,
            'stage_times' => $stageTimes,
            'filters_applied' => array_filter($filters),
        ];
    }

    public function calculateKpis(Builder $query, $complaintIds, $resolvedIds, $closedIds, $terminalIds): array
    {
        $total = $complaintIds->count();
        $resolved = (clone $query)->whereIn('status_id', $resolvedIds)->count();
        $closed = (clone $query)->whereIn('status_id', $closedIds)->count();
        $eligible = max(1, $total);
        $backlog = (clone $query)->whereNotIn('status_id', $terminalIds)->count();

        $slaBreached = SlaTracking::query()
            ->where('is_breached', true)
            ->whereIn('complaint_id', $complaintIds)
            ->distinct('complaint_id')
            ->count('complaint_id');

        $slaSubject = SlaTracking::query()->whereIn('complaint_id', $complaintIds)->distinct('complaint_id')->count('complaint_id');
        $slaCompliance = $slaSubject > 0
            ? round((($slaSubject - $slaBreached) / $slaSubject) * 100, 1)
            : 100;

        $escalated = Escalation::query()
            ->whereIn('complaint_id', $complaintIds)
            ->distinct('complaint_id')
            ->count('complaint_id');

        $validated = CaseHistory::query()
            ->whereIn('complaint_id', $complaintIds)
            ->whereIn('action_type', ['validated', 'under_validation', 'assigned'])
            ->distinct('complaint_id')
            ->count('complaint_id');

        $resolutionTimes = (clone $query)
            ->whereNotNull('closed_at')
            ->whereIn('status_id', $resolvedIds)
            ->get(['created_at', 'closed_at']);

        $hours = $resolutionTimes->map(fn ($c) => $c->created_at->diffInHours($c->closed_at))->sort()->values();
        $avgResolutionHours = $hours->isNotEmpty() ? $hours->avg() : null;
        $medianResolutionHours = $hours->isNotEmpty()
            ? $hours->get((int) floor($hours->count() / 2))
            : null;

        $reopened = CaseHistory::query()
            ->whereIn('complaint_id', $complaintIds)
            ->where('action_type', 'reopened')
            ->distinct('complaint_id')
            ->count('complaint_id');

        return [
            'resolution_rate' => $total > 0 ? round(($resolved / $eligible) * 100, 1) : 0,
            'closure_rate' => $total > 0 ? round(($closed / $eligible) * 100, 1) : 0,
            'validation_rate' => $total > 0 ? round(($validated / $eligible) * 100, 1) : 0,
            'sla_breached' => $slaBreached,
            'sla_compliance' => $slaCompliance,
            'sla_breach_rate' => $slaSubject > 0 ? round(($slaBreached / $slaSubject) * 100, 1) : 0,
            'escalation_rate' => $total > 0 ? round(($escalated / $eligible) * 100, 1) : 0,
            'backlog' => $backlog,
            'reopening_rate' => $resolved > 0 ? round(($reopened / $resolved) * 100, 1) : 0,
            'avg_resolution_days' => $avgResolutionHours ? round($avgResolutionHours / 24, 1) : null,
            'median_resolution_days' => $medianResolutionHours ? round($medianResolutionHours / 24, 1) : null,
            'avg_validation_days' => $this->avgStageDays($complaintIds, 'validated', 'submitted'),
            'avg_assignment_days' => $this->avgStageDays($complaintIds, 'assigned', 'validated'),
        ];
    }

    public function buildLeaderboard(Builder $query, $complaintIds, $resolvedIds, $terminalIds): array
    {
        return (clone $query)
            ->whereNotNull('assigned_institution_id')
            ->with(['assignedInstitution:id,institution_name,institution_type', 'status:id,is_terminal,code'])
            ->get()
            ->groupBy('assigned_institution_id')
            ->map(function ($complaints, $institutionId) use ($complaintIds, $resolvedIds, $terminalIds) {
                $total = $complaints->count();
                $resolved = $complaints->filter(fn ($c) => $resolvedIds->contains($c->status_id))->count();
                $open = $complaints->filter(fn ($c) => ! $c->status?->is_terminal)->count();
                $closedWithDates = $complaints->filter(fn ($c) => $c->closed_at);
                $avgHours = $closedWithDates->isNotEmpty()
                    ? $closedWithDates->avg(fn ($c) => $c->created_at->diffInHours($c->closed_at))
                    : null;
                $slaBreached = SlaTracking::query()
                    ->where('is_breached', true)
                    ->where('institution_id', $institutionId)
                    ->whereIn('complaint_id', $complaintIds)
                    ->count();
                $slaTotal = SlaTracking::query()
                    ->where('institution_id', $institutionId)
                    ->whereIn('complaint_id', $complaintIds)
                    ->count();
                $slaOnTime = max(0, $slaTotal - $slaBreached);

                return [
                    'institution' => $complaints->first()->assignedInstitution,
                    'total_cases' => $total,
                    'open_cases' => $open,
                    'resolved_cases' => $resolved,
                    'overdue_cases' => $complaints->filter(fn ($c) => $c->sla_deadline && $c->sla_deadline->isPast() && ! $c->status?->is_terminal)->count(),
                    'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 1) : 0,
                    'avg_resolution_days' => $avgHours ? round($avgHours / 24, 1) : null,
                    'sla_breached' => $slaBreached,
                    'sla_total' => $slaTotal,
                    'sla_on_time' => $slaOnTime,
                    'sla_pct' => $slaTotal > 0 ? round(($slaOnTime / $slaTotal) * 100, 2) : 100,
                ];
            })
            ->sortByDesc('total_cases')
            ->values()
            ->take(15)
            ->all();
    }

    public function operationalReport(Request $request, string $type, array $filters): array
    {
        $baseQuery = $this->scope->scopedComplaintsQuery($request);
        $query = $this->scope->applyFilters(clone $baseQuery, $filters);

        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        $validationCodes = ['submitted', 'under_validation'];
        $closedCodes = ['closed', 'archived'];
        $activeCodes = ['under_validation', 'assigned', 'in_processing', 'awaiting_external_input', 'escalated', 'reopened', 'additional_info_requested'];

        $rows = match ($type) {
            'open' => (clone $query)->whereNotIn('status_id', $terminalIds),
            'closed' => (clone $query)->whereHas('status', fn ($q) => $q->whereIn('code', $closedCodes)),
            'active' => (clone $query)->whereHas('status', fn ($q) => $q->whereIn('code', $activeCodes)),
            'overdue' => (clone $query)->whereNotIn('status_id', $terminalIds)
                ->where(function ($q) {
                    $q->where('sla_deadline', '<', now())
                        ->orWhereHas('slaTrackings', fn ($s) => $s->where('is_breached', true));
                }),
            'escalated' => (clone $query)->whereHas('escalations', fn ($e) => $e->whereNull('resolution_date')),
            'unassigned' => (clone $query)->whereNull('assigned_institution_id')
                ->whereHas('status', fn ($q) => $q->whereNotIn('code', ['submitted', 'rejected', 'closed', 'archived'])),
            'pending_validation' => (clone $query)->whereHas('status', fn ($q) => $q->whereIn('code', $validationCodes)),
            'reopened' => (clone $query)->whereHas('histories', fn ($h) => $h->where('action_type', 'reopened')),
            'incomplete' => (clone $query)->whereHas('status', fn ($q) => $q->where('code', 'additional_info_requested')),
            default => clone $query,
        };

        $paginated = $rows
            ->with(['status', 'category', 'assignedInstitution', 'corridor', 'location'])
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 25));

        return [
            'type' => $type,
            'rows' => $paginated->items(),
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ];
    }

    public function institutionWorkload(Request $request, array $filters): array
    {
        $dashboard = $this->buildDashboard($request, $filters);

        return collect($dashboard['leaderboard'])->map(fn ($row) => [
            'institution' => $row['institution']?->institution_name ?? '—',
            'institution_type' => $row['institution']?->institution_type,
            'open' => $row['open_cases'],
            'overdue' => $row['overdue_cases'],
            'resolved' => $row['resolved_cases'],
            'total' => $row['total_cases'],
            'sla_pct' => $row['sla_pct'],
        ])->values()->all();
    }

    public function caseHistory(int $complaintId, Request $request): ?array
    {
        $complaint = $this->scope->scopedComplaintsQuery($request)
            ->with(['status', 'category', 'assignedInstitution', 'histories.performer', 'escalations'])
            ->find($complaintId);

        if (! $complaint) {
            return null;
        }

        return [
            'complaint' => $complaint,
            'timeline' => $complaint->histories->sortBy('action_date')->values(),
            'escalations' => $complaint->escalations,
        ];
    }

    public function kpiDefinitionsWithValues(Request $request, array $filters): array
    {
        $dashboard = $this->buildDashboard($request, $filters);
        $summary = $dashboard['summary'];

        $map = [
            'total_complaints' => $summary['total'] ?? 0,
            'validated_complaints' => $summary['validation_rate'] ?? 0,
            'resolution_rate' => $summary['resolution_rate'] ?? 0,
            'closure_rate' => $summary['closure_rate'] ?? 0,
            'sla_compliance_rate' => $summary['sla_compliance'] ?? 0,
            'sla_breach_rate' => $summary['sla_breach_rate'] ?? 0,
            'escalation_rate' => $summary['escalation_rate'] ?? 0,
            'backlog' => $summary['backlog'] ?? 0,
            'avg_resolution_time' => $summary['avg_resolution_days'] ?? null,
            'median_resolution_time' => $summary['median_resolution_days'] ?? null,
            'avg_validation_time' => $summary['avg_validation_days'] ?? null,
            'avg_assignment_time' => $summary['avg_assignment_days'] ?? null,
            'reopening_rate' => $summary['reopening_rate'] ?? 0,
        ];

        return ReportKpiDefinition::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function (ReportKpiDefinition $kpi) use ($map, $dashboard) {
                $value = $map[$kpi->code] ?? null;
                $trend = 'unchanged';
                if ($kpi->target_value !== null && $value !== null) {
                    $trend = $value >= $kpi->target_value ? 'improved' : 'deteriorated';
                }

                return [
                    'code' => $kpi->code,
                    'title_en' => $kpi->title_en,
                    'title_fr' => $kpi->title_fr,
                    'description_en' => $kpi->description_en,
                    'description_fr' => $kpi->description_fr,
                    'formula' => $kpi->formula,
                    'unit' => $kpi->unit,
                    'target_value' => $kpi->target_value,
                    'warning_threshold' => $kpi->warning_threshold,
                    'value' => $value,
                    'trend' => $trend,
                    'comparison' => $dashboard['comparison'][$kpi->code] ?? null,
                ];
            })
            ->all();
    }

    public function genderInclusion(Request $request, array $filters): array
    {
        $baseQuery = $this->scope->scopedComplaintsQuery($request);
        $query = $this->scope->applyFilters(clone $baseQuery, $filters);
        $complaintIds = (clone $query)->pluck('id');
        $resolvedIds = ComplaintStatus::query()->whereIn('code', ['resolved', 'closed', 'archived'])->pluck('id');

        $groups = (clone $query)
            ->selectRaw("CASE WHEN gender IS NULL OR gender IN ('', 'NA') THEN 'not_stated' ELSE LOWER(gender) END as gender_key, COUNT(*) as total")
            ->groupBy('gender_key')
            ->get()
            ->keyBy('gender_key');

        $resolutionByGender = (clone $query)
            ->whereIn('status_id', $resolvedIds)
            ->whereNotNull('closed_at')
            ->get(['gender', 'created_at', 'closed_at'])
            ->groupBy(fn ($c) => $c->gender ?: 'not_stated')
            ->map(fn ($items) => round($items->avg(fn ($c) => $c->created_at->diffInDays($c->closed_at)), 1));

        $slaByGender = [];
        foreach ($groups->keys() as $genderKey) {
            $gFilter = $genderKey === 'not_stated' ? null : strtoupper($genderKey);
            $subset = (clone $query);
            if ($gFilter) {
                $subset->where('gender', $gFilter);
            } else {
                $subset->where(function ($q) {
                    $q->whereNull('gender')->orWhereIn('gender', ['', 'NA']);
                });
            }
            $ids = $subset->pluck('id');
            $breached = SlaTracking::query()->whereIn('complaint_id', $ids)->where('is_breached', true)->distinct('complaint_id')->count('complaint_id');
            $total = SlaTracking::query()->whereIn('complaint_id', $ids)->distinct('complaint_id')->count('complaint_id');
            $slaByGender[$genderKey] = $total > 0 ? round((($total - $breached) / $total) * 100, 1) : 100;
        }

        $channelByGender = (clone $query)
            ->selectRaw("CASE WHEN gender IS NULL OR gender IN ('', 'NA') THEN 'not_stated' ELSE LOWER(gender) END as gender_key, submission_channel, COUNT(*) as total")
            ->groupBy('gender_key', 'submission_channel')
            ->get();

        $categoryRecurrence = (clone $query)
            ->select('ntb_category_id', DB::raw('count(*) as total'))
            ->whereIn('gender', ['F', 'FEMALE', 'female'])
            ->groupBy('ntb_category_id')
            ->with('category:id,code,name_en')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $suppressed = $groups->filter(fn ($g) => $g->total < 5)->keys();

        return [
            'volumes' => $groups->values(),
            'resolution_days' => $resolutionByGender,
            'sla_compliance' => $slaByGender,
            'channels' => $channelByGender,
            'women_trader_categories' => $categoryRecurrence,
            'privacy_suppressed' => $suppressed->values(),
            'not_stated_label' => 'not_stated',
        ];
    }

    public function lookups(): array
    {
        return [
            'categories' => NtbCategory::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name_en', 'name_fr']),
            'corridors' => Corridor::query()->where('active', true)->orderBy('corridor_code')->get(['id', 'corridor_code', 'name_en', 'name_fr']),
            'institutions' => Institution::query()->where('status', 'active')->orderBy('institution_name')->get(['id', 'institution_name', 'institution_type']),
            'statuses' => ComplaintStatus::query()->orderBy('sort_order')->get(['id', 'code', 'name_en', 'name_fr']),
            'locations' => Location::query()->orderBy('location_name')->get(['id', 'location_name', 'latitude', 'longitude']),
            'periods' => [
                ['value' => 'today', 'label_en' => 'Today', 'label_fr' => "Aujourd'hui"],
                ['value' => 'this_week', 'label_en' => 'This week', 'label_fr' => 'Cette semaine'],
                ['value' => 'this_month', 'label_en' => 'This month', 'label_fr' => 'Ce mois'],
                ['value' => 'last_month', 'label_en' => 'Last month', 'label_fr' => 'Mois dernier'],
                ['value' => 'this_quarter', 'label_en' => 'This quarter', 'label_fr' => 'Ce trimestre'],
                ['value' => 'this_year', 'label_en' => 'This year', 'label_fr' => 'Cette année'],
            ],
            'genders' => [
                ['value' => 'M', 'label_en' => 'Male', 'label_fr' => 'Homme'],
                ['value' => 'F', 'label_en' => 'Female', 'label_fr' => 'Femme'],
                ['value' => 'unknown', 'label_en' => 'Not stated / Unknown', 'label_fr' => 'Non déclaré / Inconnu'],
            ],
            'channels' => ['WEB', 'SMS', 'USSD', 'EMAIL'],
            'sla_conditions' => [
                ['value' => 'within', 'label_en' => 'Within SLA', 'label_fr' => 'Dans le SLA'],
                ['value' => 'approaching', 'label_en' => 'Approaching SLA', 'label_fr' => 'SLA proche'],
                ['value' => 'breached', 'label_en' => 'SLA breached', 'label_fr' => 'SLA dépassé'],
            ],
        ];
    }

    private function yearTrend(Builder $query, int $year): Collection
    {
        $trendYear = (clone $query)
            ->whereYear('created_at', $year)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        return collect(range(1, 12))->map(function ($m) use ($year, $trendYear) {
            $key = sprintf('%04d-%02d', $year, $m);

            return ['month' => Carbon::create($year, $m)->format('M'), 'total' => (int) ($trendYear[$key]->total ?? 0)];
        });
    }

    private function comparisonPeriod(Request $request, array $filters, int $currentTotal, array $currentKpis): array
    {
        $prevFilters = $filters;
        if (! empty($filters['from_date']) && ! empty($filters['to_date'])) {
            $from = Carbon::parse($filters['from_date']);
            $days = $from->diffInDays(Carbon::parse($filters['to_date'])) + 1;
            $prevFilters['from_date'] = $from->copy()->subDays($days)->toDateString();
            $prevFilters['to_date'] = $from->copy()->subDay()->toDateString();
        } elseif (! empty($filters['year'])) {
            $prevFilters['year'] = (int) $filters['year'] - 1;
        } else {
            return [];
        }

        $baseQuery = $this->scope->scopedComplaintsQuery($request);
        $prevQuery = $this->scope->applyFilters(clone $baseQuery, $prevFilters);
        $prevIds = $prevQuery->pluck('id');
        $resolvedIds = ComplaintStatus::query()->whereIn('code', ['resolved', 'closed', 'archived'])->pluck('id');
        $closedIds = ComplaintStatus::query()->whereIn('code', ['closed', 'archived', 'rejected'])->pluck('id');
        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');
        $prevKpis = $this->calculateKpis($prevQuery, $prevIds, $resolvedIds, $closedIds, $terminalIds);

        $growth = $prevIds->count() > 0
            ? round(($currentTotal - $prevIds->count()) / $prevIds->count() * 100, 1)
            : null;

        return [
            'total_complaints' => ['current' => $currentTotal, 'previous' => $prevIds->count(), 'growth_rate' => $growth],
            'resolution_rate' => ['current' => $currentKpis['resolution_rate'] ?? 0, 'previous' => $prevKpis['resolution_rate'] ?? 0],
            'sla_compliance_rate' => ['current' => $currentKpis['sla_compliance'] ?? 0, 'previous' => $prevKpis['sla_compliance'] ?? 0],
        ];
    }

    private function ageingBands(Builder $query, $terminalIds): array
    {
        $open = (clone $query)->whereNotIn('status_id', $terminalIds)->get(['id', 'created_at']);
        $bands = [
            ['label' => '0-7 days', 'min' => 0, 'max' => 7, 'count' => 0],
            ['label' => '8-30 days', 'min' => 8, 'max' => 30, 'count' => 0],
            ['label' => '31-90 days', 'min' => 31, 'max' => 90, 'count' => 0],
            ['label' => '90+ days', 'min' => 91, 'max' => 9999, 'count' => 0],
        ];

        foreach ($open as $case) {
            $days = $case->created_at->diffInDays(now());
            foreach ($bands as &$band) {
                if ($days >= $band['min'] && $days <= $band['max']) {
                    $band['count']++;
                    break;
                }
            }
        }

        return $bands;
    }

    private function mapPoints($complaintIds): array
    {
        return Location::query()
            ->withCount(['complaints' => fn ($q) => $q->whereIn('complaints.id', $complaintIds)])
            ->having('complaints_count', '>', 0)
            ->get()
            ->map(fn (Location $loc) => [
                'label' => $loc->location_name,
                'count' => $loc->complaints_count,
                'latitude' => (float) ($loc->latitude ?? 11.58),
                'longitude' => (float) ($loc->longitude ?? 43.15),
                'severity' => $loc->complaints_count >= 5 ? 'high' : ($loc->complaints_count >= 2 ? 'medium' : 'low'),
            ])
            ->values()
            ->all();
    }

    private function dataQualityWarnings(Builder $query): array
    {
        $total = (clone $query)->count();
        if ($total === 0) {
            return [];
        }

        $warnings = [];
        $missingGender = (clone $query)->where(function ($q) {
            $q->whereNull('gender')->orWhereIn('gender', ['', 'NA']);
        })->count();
        if ($missingGender / $total > 0.3) {
            $warnings[] = ['code' => 'missing_gender', 'count' => $missingGender, 'pct' => round($missingGender / $total * 100, 1)];
        }

        $missingLocation = (clone $query)->whereNull('location_id')->count();
        if ($missingLocation / $total > 0.2) {
            $warnings[] = ['code' => 'missing_location', 'count' => $missingLocation, 'pct' => round($missingLocation / $total * 100, 1)];
        }

        $unassigned = (clone $query)->whereNull('assigned_institution_id')->count();
        if ($unassigned > 0) {
            $warnings[] = ['code' => 'unassigned_cases', 'count' => $unassigned, 'pct' => round($unassigned / $total * 100, 1)];
        }

        return $warnings;
    }

    private function recurrenceAnalysis(Builder $query): array
    {
        return (clone $query)
            ->select('ntb_category_id', 'location_id', DB::raw('count(*) as total'))
            ->groupBy('ntb_category_id', 'location_id')
            ->having('total', '>', 1)
            ->with(['category:id,code,name_en', 'location:id,location_name'])
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category?->code,
                'location' => $row->location?->location_name ?? '—',
                'occurrences' => $row->total,
            ])
            ->all();
    }

    private function escalationSummary($complaintIds): array
    {
        return Escalation::query()
            ->whereIn('complaint_id', $complaintIds)
            ->select('escalation_level', 'escalation_type', DB::raw('count(*) as total'))
            ->groupBy('escalation_level', 'escalation_type')
            ->orderBy('escalation_level')
            ->get()
            ->all();
    }

    private function stageTimeAnalysis($complaintIds): array
    {
        $stages = ['validation', 'assignment', 'processing', 'escalation', 'closure'];
        $result = [];

        foreach ($stages as $stage) {
            $trackings = SlaTracking::query()
                ->whereIn('complaint_id', $complaintIds)
                ->where('stage', 'like', "%{$stage}%")
                ->whereNotNull('completed_at')
                ->get();

            $avgHours = $trackings->isNotEmpty()
                ? round($trackings->avg(fn ($t) => $t->started_at->diffInHours($t->completed_at)), 1)
                : null;

            $result[] = ['stage' => $stage, 'avg_hours' => $avgHours, 'cases' => $trackings->count()];
        }

        return $result;
    }

    private function avgStageDays($complaintIds, string $toAction, string $fromAction): ?float
    {
        $pairs = CaseHistory::query()
            ->whereIn('complaint_id', $complaintIds)
            ->whereIn('action_type', [$fromAction, $toAction])
            ->orderBy('action_date')
            ->get()
            ->groupBy('complaint_id');

        $durations = [];
        foreach ($pairs as $histories) {
            $from = $histories->firstWhere('action_type', $fromAction);
            $to = $histories->firstWhere('action_type', $toAction);
            if ($from && $to && $to->action_date->gt($from->action_date)) {
                $durations[] = $from->action_date->diffInDays($to->action_date);
            }
        }

        return count($durations) > 0 ? round(array_sum($durations) / count($durations), 1) : null;
    }
}
