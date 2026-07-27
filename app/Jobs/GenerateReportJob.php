<?php

namespace App\Jobs;

use App\Models\ReportInstance;
use App\Services\Ntb\Reports\ReportGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;

class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $instanceId,
        public ?int $userId = null,
    ) {}

    public function handle(ReportGeneratorService $generator): void
    {
        $instance = ReportInstance::query()->with('definition')->find($this->instanceId);
        if (! $instance || $instance->status === 'cancelled') {
            return;
        }

        $request = Request::create('/', 'GET');
        if ($this->userId) {
            $request->setUserResolver(fn () => \App\Models\User::query()->find($this->userId));
        }

        $generator->processInstance($instance, $request);
    }
}
