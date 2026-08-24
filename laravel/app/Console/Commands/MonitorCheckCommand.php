<?php

namespace App\Console\Commands;

use App\Console\Output\Reporter;
use Domain\Collector\CollectionReport;
use Domain\Collector\Collector;
use Domain\SiteRegistry\SiteRegistry;
use Illuminate\Console\Command;

/**
 * Maps the vanilla `bin/mediadev check all|list` CLI.
 *
 * Exit codes: 0 = OK, 1 = DOWN or RED-critical, 2 = usage/config error.
 * `check all` renders the same terminal table as the vanilla Reporter;
 * `list` prints registered sites (id, url, type).
 */
class MonitorCheckCommand extends Command
{
    protected $signature = 'monitor:check {target?} {--list}';

    protected $description = 'Check all monitored sites (deep collection) or list registered sites';

    public function handle(Collector $collector, SiteRegistry $registry): int
    {
        if ($this->option('list')) {
            try {
                $sites = $registry->all();
            } catch (\RuntimeException $e) {
                $this->error("ERROR de configuración: {$e->getMessage()}");

                return 2;
            }

            foreach ($sites as $site) {
                $this->line(sprintf("%-4d %-40s %-8s", $site->id, $site->url, $site->type));
            }

            return 0;
        }

        $target = $this->argument('target');
        if (!in_array($target, ['all', null], true)) {
            $this->error("Uso: artisan monitor:check all");

            return 2;
        }

        try {
            $report = $collector->runAll('deep');
        } catch (\RuntimeException $e) {
            $this->error("ERROR de configuración: {$e->getMessage()}");

            return 2;
        }

        $this->output->write((new Reporter())->render($report));

        return $report->hasCritical() ? 1 : 0;
    }
}
