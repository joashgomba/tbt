<?php

namespace App\Console\Commands;

use App\Jobs\GenerateReportJob;
use App\Models\ReportSchedule;
use App\Services\Ntb\Reports\ReportGeneratorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class RunScheduledReportsCommand extends Command
{
    protected $signature = 'ntb:run-scheduled-reports';

    protected $description = 'Generate and deliver scheduled NTB reports';

    public function handle(ReportGeneratorService $generator): int
    {
        $due = ReportSchedule::query()
            ->with('definition')
            ->where('is_active', true)
            ->where('is_suspended', false)
            ->where(function ($q) {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->get();

        foreach ($due as $schedule) {
            $request = Request::create('/', 'GET');
            $request->setUserResolver(fn () => $schedule->owner);

            $instance = $generator->createInstance(
                $schedule->definition,
                $request,
                $schedule->parameters ?? [],
                $schedule->output_format ?? 'pdf',
            );

            GenerateReportJob::dispatch($instance->id, $schedule->owner_id);

            $schedule->update([
                'last_run_at' => now(),
                'next_run_at' => $this->nextRun($schedule->frequency),
            ]);

            $this->info("Queued {$schedule->definition->code} for schedule #{$schedule->id}");
        }

        return self::SUCCESS;
    }

    private function nextRun(string $frequency): Carbon
    {
        return match ($frequency) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            'annual' => now()->addYear(),
            default => now()->addDay(),
        };
    }
}
