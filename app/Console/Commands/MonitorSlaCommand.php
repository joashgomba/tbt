<?php

namespace App\Console\Commands;

use App\Services\Ntb\SlaMonitorService;
use Illuminate\Console\Command;

class MonitorSlaCommand extends Command
{
    protected $signature = 'ntb:monitor-sla';

    protected $description = 'Mark breached SLA stages and trigger automatic escalations';

    public function handle(SlaMonitorService $monitor): int
    {
        $count = $monitor->run();
        $this->info("Processed {$count} SLA breach(es).");

        return self::SUCCESS;
    }
}
