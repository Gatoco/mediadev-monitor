<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Runs the deep collection (degradation + versions + health + activity) every 6 hours.
 *
 * Signature: collector:deep
 */
class CollectorDeepCommand extends BaseCollectorCommand
{
    protected $signature = 'collector:deep';

    protected $description = 'Run deep collection (degradation, versions, health, activity) for all sites (6h cadence).';

    protected function collectorMode(): string
    {
        return 'deep';
    }
}
