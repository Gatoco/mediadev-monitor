<?php

namespace App\Console\Commands;

use Domain\Collector\Collector;
use Domain\SiteRegistry\SiteRegistry;
use Illuminate\Console\Command;
use Throwable;

/**
 * Shared collector behaviour for the artisan monitoring commands.
 *
 * Exit-code contract (preserved from bin/collector.php / bin/mediadev):
 *   0 = no site critical
 *   1 = at least one site critical (DOWN or versions.severity === 'red')
 *   2 = configuration or runtime error
 *
 * Output contract (grep-compatible for the E2E harness):
 *   one line per site: "<name>  <state>"
 */
abstract class BaseCollectorCommand extends Command
{
    /** Collector mode for this command: 'uptime' or 'deep'. */
    abstract protected function collectorMode(): string;

    public function handle(): int
    {
        try {
            $sites = config('sites.sites');

            if (empty($sites)) {
                throw new \RuntimeException('Site configuration missing: config/sites.php defines no sites.');
            }

            /** @var SiteRegistry $registry */
            $registry = app(SiteRegistry::class);
            $registry->syncFromConfig($sites);

            /** @var Collector $collector */
            $collector = app(Collector::class);
            $report = $collector->runAll($this->collectorMode());
        } catch (\RuntimeException $e) {
            $this->error("ERROR de configuración: {$e->getMessage()}");

            return 2;
        } catch (Throwable $e) {
            $this->error("ERROR de ejecución: {$e->getMessage()}");

            return 2;
        }

        foreach ($report->sites as $siteReport) {
            // Two-space separator keeps `awk '{print $2}'` parsing stable.
            $this->line(sprintf('%s  %s', $siteReport->site->name, $siteReport->state->value));
        }

        return $report->hasCritical() ? 1 : 0;
    }
}
