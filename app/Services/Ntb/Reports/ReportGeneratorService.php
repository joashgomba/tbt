<?php

namespace App\Services\Ntb\Reports;

use App\Models\ReportDefinition;
use App\Models\ReportExportLog;
use App\Models\ReportInstance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class ReportGeneratorService
{
    public function __construct(
        private readonly ReportAnalyticsService $analytics,
    ) {}

    public function createInstance(ReportDefinition $definition, Request $request, array $filters, string $format = 'pdf'): ReportInstance
    {
        $instance = ReportInstance::query()->create([
            'report_definition_id' => $definition->id,
            'instance_code' => $this->generateInstanceCode($definition->code),
            'status' => 'queued',
            'parameters' => ['format' => $format],
            'filters' => $filters,
            'generated_by' => $request->user()?->id,
            'generated_at' => now(),
            'file_format' => $format,
            'metadata' => [
                'title' => $definition->localizedTitle($request->input('locale')),
                'confidentiality' => $definition->confidentiality,
                'report_code' => $definition->code,
            ],
        ]);

        return $instance;
    }

    public function processInstance(ReportInstance $instance, Request $request): ReportInstance
    {
        $instance->update(['status' => 'processing']);
        $definition = $instance->definition;
        $filters = $instance->filters ?? [];
        $format = $instance->file_format ?? 'pdf';
        $locale = $request->input('locale', app()->getLocale());

        try {
            $payload = $this->buildReportPayload($definition, $request, $filters, $locale);
            $path = $this->exportPayload($payload, $definition, $format, $instance->instance_code);

            $instance->update([
                'status' => 'completed',
                'completed_at' => now(),
                'file_path' => $path,
                'metadata' => array_merge($instance->metadata ?? [], [
                    'row_count' => count($payload['rows'] ?? []),
                    'generated_at_display' => now()->toIso8601String(),
                ]),
            ]);

            ReportExportLog::query()->create([
                'user_id' => $instance->generated_by,
                'report_instance_id' => $instance->id,
                'report_code' => $definition->code,
                'format' => $format,
                'filters' => $filters,
                'status' => 'completed',
                'file_path' => $path,
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            $instance->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            ReportExportLog::query()->create([
                'user_id' => $instance->generated_by,
                'report_instance_id' => $instance->id,
                'report_code' => $definition->code,
                'format' => $format,
                'filters' => $filters,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'ip_address' => $request->ip(),
            ]);
        }

        return $instance->fresh(['definition', 'generator']);
    }

    public function preview(ReportDefinition $definition, Request $request, array $filters): array
    {
        $locale = $request->input('locale', app()->getLocale());

        return $this->buildReportPayload($definition, $request, $filters, $locale);
    }

    private function buildReportPayload(ReportDefinition $definition, Request $request, array $filters, string $locale): array
    {
        $dashboard = $this->analytics->buildDashboard($request, $filters);
        $category = $definition->category;
        $type = $this->operationalTypeFromCode($definition->code);

        $rows = [];
        if ($type) {
            $filters['per_page'] = 500;
            $operational = $this->analytics->operationalReport($request, $type, $filters);
            $rows = collect($operational['rows'])->map(fn ($c) => $this->maskComplaintRow($c, $request))->all();
        } elseif ($definition->code === 'RPT-08') {
            $rows = $this->analytics->institutionWorkload($request, $filters);
        } elseif (in_array($definition->code, ['RPT-10', 'RPT-11'], true)) {
            $rows = $this->regionalExportRows($dashboard);
        }

        return [
            'report' => [
                'code' => $definition->code,
                'title' => $locale === 'fr' ? $definition->title_fr : $definition->title_en,
                'category' => $category,
                'confidentiality' => $definition->confidentiality,
                'generated_at' => now()->toIso8601String(),
                'timezone' => config('app.timezone'),
                'filters' => array_filter($filters),
            ],
            'summary' => $dashboard['summary'],
            'kpis' => $this->analytics->kpiDefinitionsWithValues($request, $filters),
            'leaderboard' => $dashboard['leaderboard'],
            'charts' => [
                'by_month' => $dashboard['by_month'],
                'by_category' => $dashboard['by_category'],
                'by_corridor' => $dashboard['by_corridor'],
                'by_gender' => $dashboard['by_gender'],
            ],
            'rows' => $rows,
            'quality_warnings' => $dashboard['quality_warnings'],
        ];
    }

    private function exportPayload(array $payload, ReportDefinition $definition, string $format, string $instanceCode): string
    {
        $dir = 'reports/'.now()->format('Y/m');
        Storage::disk('local')->makeDirectory($dir);
        $base = $dir.'/'.$instanceCode;

        return match ($format) {
            'xlsx', 'excel' => $this->exportExcel($payload, $base.'.xlsx'),
            'csv' => $this->exportCsv($payload, $base.'.csv'),
            'json' => $this->exportJson($payload, $base.'.json'),
            default => $this->exportPdf($payload, $definition, $base.'.pdf'),
        };
    }

    private function exportPdf(array $payload, ReportDefinition $definition, string $path): string
    {
        $html = view('reports.document', ['payload' => $payload])->render();
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    private function exportCsv(array $payload, string $path): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');
        $sheet->fromArray(['Report', $payload['report']['code'] ?? '']);
        $sheet->fromArray(['Generated', $payload['report']['generated_at'] ?? ''], null, 'A2');

        $row = 4;
        if (! empty($payload['rows'])) {
            $headers = array_keys((array) ($payload['rows'][0] ?? []));
            $sheet->fromArray($headers, null, 'A'.$row);
            $row++;
            foreach ($payload['rows'] as $dataRow) {
                $sheet->fromArray(array_values((array) $dataRow), null, 'A'.$row);
                $row++;
            }
        }

        $writer = new CsvWriter($spreadsheet);
        $full = storage_path('app/'.$path);
        $writer->save($full);

        return $path;
    }

    private function exportExcel(array $payload, string $path): string
    {
        $spreadsheet = new Spreadsheet;
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Summary');
        $summary->setCellValue('A1', $payload['report']['title'] ?? 'Report');
        $summary->setCellValue('A2', 'Code: '.($payload['report']['code'] ?? ''));
        $summary->setCellValue('A3', 'Generated: '.($payload['report']['generated_at'] ?? ''));

        $r = 5;
        foreach ($payload['summary'] ?? [] as $key => $value) {
            $summary->setCellValue('A'.$r, $key);
            $summary->setCellValue('B'.$r, is_scalar($value) ? $value : json_encode($value));
            $r++;
        }

        if (! empty($payload['rows'])) {
            $dataSheet = $spreadsheet->createSheet();
            $dataSheet->setTitle('Data');
            $headers = array_keys((array) ($payload['rows'][0] ?? []));
            $dataSheet->fromArray($headers, null, 'A1');
            $row = 2;
            foreach ($payload['rows'] as $dataRow) {
                $dataSheet->fromArray(array_values((array) $dataRow), null, 'A'.$row);
                $row++;
            }
        }

        $writer = new XlsxWriter($spreadsheet);
        $full = storage_path('app/'.$path);
        $writer->save($full);

        return $path;
    }

    private function exportJson(array $payload, string $path): string
    {
        Storage::disk('local')->put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function generateInstanceCode(string $reportCode): string
    {
        return strtoupper($reportCode).'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function operationalTypeFromCode(string $code): ?string
    {
        return match ($code) {
            'RPT-01', 'RPT-02' => 'open',
            'RPT-03' => 'overdue',
            'RPT-04' => 'escalated',
            'RPT-05' => null,
            'RPT-06', 'RPT-07', 'RPT-08' => null,
            'RPT-20' => 'open',
            default => null,
        };
    }

    private function maskComplaintRow($complaint, Request $request): array
    {
        $canViewPii = $request->user()?->can('complaints.view_all') ?? false;

        return [
            'code' => $complaint->complaint_code,
            'title' => $complaint->title,
            'category' => $complaint->category?->code,
            'status' => $complaint->status?->code,
            'priority' => $complaint->priority,
            'institution' => $complaint->assignedInstitution?->institution_name,
            'corridor' => $complaint->corridor?->corridor_code,
            'location' => $complaint->location?->location_name,
            'reporter' => $canViewPii ? ($complaint->reporter_name ?? 'Anonymous') : '—',
            'gender' => $complaint->gender ?: 'not_stated',
            'channel' => $complaint->submission_channel,
            'created_at' => $complaint->created_at?->toIso8601String(),
            'closed_at' => $complaint->closed_at?->toIso8601String(),
        ];
    }

    private function regionalExportRows(array $dashboard): array
    {
        return collect($dashboard['by_category'] ?? [])->map(fn ($row) => [
            'category_code' => $row->category?->code,
            'category_name' => $row->category?->name_en,
            'case_count' => $row->count,
            'reporting_period' => now()->format('Y-m'),
        ])->all();
    }
}
