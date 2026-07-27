<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportJob;
use App\Models\ReportDefinition;
use App\Models\ReportExportLog;
use App\Models\ReportInstance;
use App\Models\ReportKpiDefinition;
use App\Models\ReportSavedFilter;
use App\Models\ReportSchedule;
use App\Services\Ntb\AuditLogger;
use App\Services\Ntb\Reports\ReportAnalyticsService;
use App\Services\Ntb\Reports\ReportGeneratorService;
use App\Services\Ntb\Reports\ReportScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportScopeService $scope,
        private readonly ReportAnalyticsService $analytics,
        private readonly ReportGeneratorService $generator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $filters = $this->extractFilters($request);
        $dashboard = $this->analytics->buildDashboard($request, $filters);

        $instancesTotal = ReportInstance::query()->where('generated_by', $request->user()?->id)->count();
        $instancesFailed = ReportInstance::query()
            ->where('generated_by', $request->user()?->id)
            ->where('status', 'failed')
            ->count();
        $schedulesActive = ReportSchedule::query()
            ->where('owner_id', $request->user()?->id)
            ->where('is_active', true)
            ->where('is_suspended', false)
            ->count();

        return response()->json([
            'summary' => array_merge($dashboard['summary'], [
                'reports_generated' => $instancesTotal,
                'reports_failed' => $instancesFailed,
                'schedules_active' => $schedulesActive,
                'catalogue_count' => $this->accessibleDefinitions($request)->count(),
            ]),
            'leaderboard' => $dashboard['leaderboard'],
            'cutoff_at' => $dashboard['cutoff_at'],
            'timezone' => $dashboard['timezone'],
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        $this->authorizeReport($request);

        return response()->json($this->analytics->lookups());
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorizeReport($request);

        return response()->json($this->analytics->buildDashboard($request, $this->extractFilters($request)));
    }

    public function catalogue(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $locale = $request->input('locale', app()->getLocale());

        $items = $this->accessibleDefinitions($request)->map(fn (ReportDefinition $def) => [
            'id' => $def->id,
            'code' => $def->code,
            'category' => $def->category,
            'title' => $locale === 'fr' ? $def->title_fr : $def->title_en,
            'description' => $locale === 'fr' ? $def->description_fr : $def->description_en,
            'default_frequency' => $def->default_frequency,
            'output_formats' => $def->output_formats,
            'confidentiality' => $def->confidentiality,
            'chart_types' => $def->chart_types,
        ]);

        return response()->json(['catalogue' => $items]);
    }

    public function kpis(Request $request): JsonResponse
    {
        $this->authorizeReport($request);

        return response()->json([
            'kpis' => $this->analytics->kpiDefinitionsWithValues($request, $this->extractFilters($request)),
            'cutoff_at' => now()->toIso8601String(),
        ]);
    }

    public function operational(Request $request, string $type): JsonResponse
    {
        $this->authorizeReport($request);

        return response()->json(
            $this->analytics->operationalReport($request, $type, $this->extractFilters($request))
        );
    }

    public function institutionWorkload(Request $request): JsonResponse
    {
        $this->authorizeReport($request);

        return response()->json([
            'rows' => $this->analytics->institutionWorkload($request, $this->extractFilters($request)),
        ]);
    }

    public function genderInclusion(Request $request): JsonResponse
    {
        $this->authorizeReport($request);

        return response()->json(
            $this->analytics->genderInclusion($request, $this->extractFilters($request))
        );
    }

    public function caseHistory(Request $request, int $id): JsonResponse
    {
        $this->authorizeReport($request);
        $data = $this->analytics->caseHistory($id, $request);
        abort_unless($data, 404);

        return response()->json($data);
    }

    public function cases(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $filters = $this->extractFilters($request);
        $filters['per_page'] = $request->integer('per_page', 25);

        return response()->json(
            $this->analytics->operationalReport($request, $request->input('type', 'open'), $filters)
        );
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $definition = $this->findAccessibleDefinition($request, $request->integer('report_definition_id'));

        $payload = $this->generator->preview($definition, $request, $this->extractFilters($request));
        $this->auditLogger->log('report.preview', $definition, null, ['code' => $definition->code]);

        return response()->json($payload);
    }

    public function generate(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $definition = $this->findAccessibleDefinition($request, $request->integer('report_definition_id'));
        $format = $request->input('format', 'pdf');
        $filters = $this->extractFilters($request);
        $async = $request->boolean('async', true);

        $instance = $this->generator->createInstance($definition, $request, $filters, $format);
        $this->auditLogger->log('report.generate', $instance, null, [
            'code' => $definition->code,
            'format' => $format,
            'filters' => $filters,
        ]);

        if ($async) {
            GenerateReportJob::dispatch($instance->id, $request->user()?->id);

            return response()->json([
                'instance' => $instance->load('definition'),
                'message' => 'Report queued for background generation.',
            ], 202);
        }

        $instance = $this->generator->processInstance($instance, $request);

        return response()->json(['instance' => $instance->load('definition')]);
    }

    public function instances(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $query = ReportInstance::query()->with(['definition', 'generator:id,name']);

        if (! $request->user()?->can('reports.admin')) {
            $query->where('generated_by', $request->user()?->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginated = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json($paginated);
    }

    public function showInstance(Request $request, int $id): JsonResponse
    {
        $this->authorizeReport($request);
        $instance = $this->findAccessibleInstance($request, $id);

        return response()->json($instance->load(['definition', 'generator:id,name', 'exportLogs']));
    }

    public function downloadInstance(Request $request, int $id)
    {
        $this->authorizeExport($request);
        $instance = $this->findAccessibleInstance($request, $id);
        abort_unless($instance->status === 'completed' && $instance->file_path, 404);

        $this->auditLogger->log('report.download', $instance);

        return Storage::disk('local')->download(
            $instance->file_path,
            basename($instance->file_path),
        );
    }

    public function retryInstance(Request $request, int $id): JsonResponse
    {
        $this->authorizeReport($request);
        $instance = $this->findAccessibleInstance($request, $id);
        abort_unless($instance->status === 'failed', 422);

        $instance->update(['status' => 'queued', 'error_message' => null]);
        GenerateReportJob::dispatch($instance->id, $request->user()?->id);

        return response()->json(['instance' => $instance->fresh()]);
    }

    public function schedules(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $query = ReportSchedule::query()->with(['definition', 'owner:id,name']);

        if (! $request->user()?->can('reports.admin')) {
            $query->where('owner_id', $request->user()?->id);
        }

        return response()->json(['schedules' => $query->latest()->get()]);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $data = $request->validate([
            'report_definition_id' => 'required|exists:report_definitions,id',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly,annual,custom',
            'output_format' => 'required|in:pdf,xlsx,csv,json',
            'parameters' => 'nullable|array',
            'recipients' => 'nullable|array',
            'channels' => 'nullable|array',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]);

        $definition = $this->findAccessibleDefinition($request, (int) $data['report_definition_id']);

        $schedule = ReportSchedule::query()->create([
            ...$data,
            'owner_id' => $request->user()->id,
            'next_run_at' => now()->addDay(),
            'channels' => $data['channels'] ?? ['portal'],
        ]);

        $this->auditLogger->log('report.schedule.create', $schedule, null, ['code' => $definition->code]);

        return response()->json(['schedule' => $schedule->load('definition')], 201);
    }

    public function updateSchedule(Request $request, int $id): JsonResponse
    {
        $this->authorizeReport($request);
        $schedule = $this->findAccessibleSchedule($request, $id);
        $data = $request->validate([
            'frequency' => 'sometimes|in:daily,weekly,monthly,quarterly,annual,custom',
            'output_format' => 'sometimes|in:pdf,xlsx,csv,json',
            'parameters' => 'nullable|array',
            'recipients' => 'nullable|array',
            'channels' => 'nullable|array',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
        ]);

        $schedule->update($data);
        $this->auditLogger->log('report.schedule.update', $schedule);

        return response()->json(['schedule' => $schedule->fresh()->load('definition')]);
    }

    public function suspendSchedule(Request $request, int $id): JsonResponse
    {
        $this->authorizeReport($request);
        $schedule = $this->findAccessibleSchedule($request, $id);
        $schedule->update(['is_suspended' => true]);
        $this->auditLogger->log('report.schedule.suspend', $schedule);

        return response()->json(['schedule' => $schedule]);
    }

    public function resumeSchedule(Request $request, int $id): JsonResponse
    {
        $this->authorizeReport($request);
        $schedule = $this->findAccessibleSchedule($request, $id);
        $schedule->update(['is_suspended' => false, 'next_run_at' => now()->addDay()]);
        $this->auditLogger->log('report.schedule.resume', $schedule);

        return response()->json(['schedule' => $schedule]);
    }

    public function deleteSchedule(Request $request, int $id): JsonResponse
    {
        $this->authorizeReport($request);
        $schedule = $this->findAccessibleSchedule($request, $id);
        $this->auditLogger->log('report.schedule.delete', $schedule);
        $schedule->delete();

        return response()->json(['message' => 'Schedule cancelled.']);
    }

    public function savedFilters(Request $request): JsonResponse
    {
        $this->authorizeReport($request);

        $filters = ReportSavedFilter::query()
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                    ->orWhere('is_shared', true);
            })
            ->latest()
            ->get();

        return response()->json(['saved_filters' => $filters]);
    }

    public function storeSavedFilter(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'filters' => 'required|array',
            'is_shared' => 'boolean',
        ]);

        $filter = ReportSavedFilter::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
            'is_shared' => $data['is_shared'] ?? false,
        ]);

        return response()->json(['saved_filter' => $filter], 201);
    }

    public function deleteSavedFilter(Request $request, int $id): JsonResponse
    {
        $this->authorizeReport($request);
        $filter = ReportSavedFilter::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
        $filter->delete();

        return response()->json(['message' => 'Filter removed.']);
    }

    public function exportLogs(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('reports.export') || $request->user()?->can('audit.view'), 403);

        $query = ReportExportLog::query()->with(['user:id,name', 'instance.definition']);

        if (! $request->user()?->can('audit.view')) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json(['logs' => $query->latest()->limit(100)->get()]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorizeExport($request);
        $filters = $this->extractFilters($request);
        $filename = 'ntb-report-'.now()->format('Ymd-His').'.csv';

        $this->logExport($request, 'csv', $filters);

        $query = $this->scope->applyFilters(
            $this->scope->scopedComplaintsQuery($request),
            $filters,
        )->with(['category', 'status', 'corridor', 'assignedInstitution']);

        return response()->streamDownload(function () use ($query, $request) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Title', 'Category', 'Status', 'Priority', 'Institution', 'Corridor', 'Gender', 'Channel', 'Created At', 'Closed At']);

            $canViewPii = $request->user()?->can('complaints.view_all') ?? false;

            $query->orderBy('id')->chunk(200, function ($rows) use ($handle, $canViewPii) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->complaint_code,
                        $row->title,
                        $row->category?->code,
                        $row->status?->code,
                        $row->priority,
                        $row->assignedInstitution?->institution_name,
                        $row->corridor?->corridor_code,
                        $row->gender ?: 'not_stated',
                        $row->submission_channel,
                        $row->created_at,
                        $row->closed_at,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportExcel(Request $request)
    {
        $this->authorizeExport($request);
        $filters = $this->extractFilters($request);
        $definition = ReportDefinition::query()->where('code', 'RPT-06')->firstOrFail();
        $instance = $this->generator->createInstance($definition, $request, $filters, 'xlsx');
        $instance = $this->generator->processInstance($instance, $request);
        $this->logExport($request, 'xlsx', $filters, $instance->id);

        return Storage::disk('local')->download($instance->file_path, basename($instance->file_path));
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeExport($request);
        $filters = $this->extractFilters($request);
        $definition = ReportDefinition::query()->where('code', 'RPT-06')->firstOrFail();
        $instance = $this->generator->createInstance($definition, $request, $filters, 'pdf');
        $instance = $this->generator->processInstance($instance, $request);
        $this->logExport($request, 'pdf', $filters, $instance->id);

        return Storage::disk('local')->download($instance->file_path, basename($instance->file_path));
    }

    public function adminDefinitions(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('reports.admin'), 403);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'code' => 'required|string|max:20|unique:report_definitions,code',
                'category' => 'required|string',
                'title_en' => 'required|string',
                'title_fr' => 'required|string',
                'default_frequency' => 'required|string',
                'output_formats' => 'nullable|array',
                'confidentiality' => 'required|string',
                'is_active' => 'boolean',
            ]);
            $def = ReportDefinition::query()->create($data);
            $this->auditLogger->log('report.definition.create', $def);

            return response()->json(['definition' => $def], 201);
        }

        return response()->json(['definitions' => ReportDefinition::query()->orderBy('sort_order')->get()]);
    }

    public function updateDefinition(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('reports.admin'), 403);
        $def = ReportDefinition::query()->findOrFail($id);
        $data = $request->validate([
            'title_en' => 'sometimes|string',
            'title_fr' => 'sometimes|string',
            'description_en' => 'nullable|string',
            'description_fr' => 'nullable|string',
            'default_frequency' => 'sometimes|string',
            'output_formats' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'confidentiality' => 'sometimes|string',
        ]);
        $def->update($data);
        $this->auditLogger->log('report.definition.update', $def);

        return response()->json(['definition' => $def]);
    }

    public function adminKpiDefinitions(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('reports.admin'), 403);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'code' => 'required|string|unique:report_kpi_definitions,code',
                'title_en' => 'required|string',
                'title_fr' => 'required|string',
                'formula' => 'required|string',
                'unit' => 'nullable|string',
                'target_value' => 'nullable|numeric',
                'warning_threshold' => 'nullable|numeric',
            ]);
            $kpi = ReportKpiDefinition::query()->create($data);

            return response()->json(['kpi' => $kpi], 201);
        }

        return response()->json(['kpis' => ReportKpiDefinition::query()->orderBy('code')->get()]);
    }

    public function updateKpiDefinition(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('reports.admin'), 403);
        $kpi = ReportKpiDefinition::query()->findOrFail($id);
        $old = $kpi->only(['formula', 'target_value', 'version']);
        $data = $request->validate([
            'title_en' => 'sometimes|string',
            'title_fr' => 'sometimes|string',
            'formula' => 'sometimes|string',
            'target_value' => 'nullable|numeric',
            'warning_threshold' => 'nullable|numeric',
            'is_active' => 'sometimes|boolean',
        ]);
        if (isset($data['formula']) && $data['formula'] !== $kpi->formula) {
            $data['version'] = $kpi->version + 1;
        }
        $kpi->update($data);
        $this->auditLogger->log('report.kpi.update', $kpi, $old, $kpi->fresh()->only(['formula', 'target_value', 'version']));

        return response()->json(['kpi' => $kpi]);
    }

    public function summary(Request $request): JsonResponse
    {
        return $this->dashboard($request);
    }

    private function extractFilters(Request $request): array
    {
        return array_filter($request->only([
            'period', 'from_date', 'to_date', 'from_month', 'to_month', 'year', 'month', 'quarter',
            'date_field', 'category_id', 'corridor_id', 'institution_id', 'location_id',
            'status_id', 'status_codes', 'priority', 'gender', 'submission_channel',
            'search', 'sla_condition', 'escalation_level', 'open_only', 'closed_only',
        ]), fn ($v) => $v !== null && $v !== '');
    }

    private function accessibleDefinitions(Request $request)
    {
        return ReportDefinition::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ReportDefinition $def) => $this->scope->canAccessDefinition(
                $request->user(),
                $def->required_permissions ?? ['reports.generate']
            ))
            ->values();
    }

    private function findAccessibleDefinition(Request $request, int $id): ReportDefinition
    {
        $def = ReportDefinition::query()->findOrFail($id);
        abort_unless(
            $this->scope->canAccessDefinition($request->user(), $def->required_permissions ?? ['reports.generate']),
            403
        );

        return $def;
    }

    private function findAccessibleInstance(Request $request, int $id): ReportInstance
    {
        $instance = ReportInstance::query()->with('definition')->findOrFail($id);
        if (! $request->user()?->can('reports.admin') && $instance->generated_by !== $request->user()?->id) {
            abort(403);
        }

        return $instance;
    }

    private function findAccessibleSchedule(Request $request, int $id): ReportSchedule
    {
        $schedule = ReportSchedule::query()->findOrFail($id);
        if (! $request->user()?->can('reports.admin') && $schedule->owner_id !== $request->user()?->id) {
            abort(403);
        }

        return $schedule;
    }

    private function authorizeReport(Request $request): void
    {
        abort_unless($request->user()?->can('reports.generate'), 403);
    }

    private function authorizeExport(Request $request): void
    {
        abort_unless($request->user()?->can('reports.export'), 403);
    }

    private function logExport(Request $request, string $format, array $filters, ?int $instanceId = null): void
    {
        ReportExportLog::query()->create([
            'user_id' => $request->user()?->id,
            'report_instance_id' => $instanceId,
            'format' => $format,
            'filters' => $filters,
            'status' => 'completed',
            'ip_address' => $request->ip(),
        ]);
        $this->auditLogger->log('report.export', null, null, ['format' => $format, 'filters' => $filters]);
    }
}
