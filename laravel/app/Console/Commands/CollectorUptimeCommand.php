<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Runs the uptime (HTTP) collection every 5 minutes via the scheduler.
 *
 * Signature: collector:uptime
 */
class CollectorUptimeCommand extends BaseCollectorCommand
{
    protected $signature = 'collector:uptime';

    protected $description = 'Run uptime (HTTP) collection for all sites (5-min cadence).';

    protected function collectorMode(): string
    {
        return 'uptime';
    }
}
