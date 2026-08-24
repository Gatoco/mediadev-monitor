<?php

namespace App\Console\Commands;

use Domain\Collector\Collector;
use Domain\SiteRegistry\SiteState;
use Illuminate\Console\Command;

/**
 * Maps the vanilla `php bin/collector.php deep` (cron every 6 hours).
 *
 * Exit codes: 0 = OK, 1 = DOWN or RED-critical, 2 = config error.
 * Output: one row per site in `name  state` format (machine-readable),
 * identical to the vanilla collector.
 */
class CollectorDeepCommand extends Command
{
    protected $signature = 'collector:deep';

    protected $description = 'Run deep collection (versions + health + activity + degradation)';

    public function handle(Collector $collector): int
    {
        try {
            $report = $collector->runAll('deep');
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
