<?php

namespace App\Console\Commands;

use Domain\Collector\Collector;
use Domain\SiteRegistry\SiteState;
use Illuminate\Console\Command;

/**
 * Maps the vanilla `php bin/collector.php uptime` (cron every 5 minutes).
 *
 * Exit codes: 0 = OK, 1 = at least one site DOWN, 2 = config error.
 * Output: one row per site in `name  state` format (machine-readable).
 */
class CollectorUptimeCommand extends Command
{
    protected $signature = 'collector:uptime';

    protected $description = 'Run uptime HTTP checks (5-minute cadence)';

    public function handle(Collector $collector): int
    {
        try {
            $report = $collector->runAll('uptime');
        } catch (\RuntimeException $e) {
            $this->error("ERROR de configuración: {$e->getMessage()}");

            return 2;
        }

        foreach ($report->sites as $siteReport) {
            $this->line(sprintf(
                "%-32s %-12s %s",
                $siteReport->site->name,
                $siteReport->state->value,
                $siteReport->state === SiteState::DOWN ? 'DOWN' : ''
            ));
        }

        return $report->hasCritical() ? 1 : 0;
    }
}
