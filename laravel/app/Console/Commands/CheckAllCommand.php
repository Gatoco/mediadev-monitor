<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Runs all collectors for every site (deep mode). Replaces `bin/mediadev check all`.
 *
 * Signature: monitor:check {target?}
 *   - `all` or omitted: collect every site (default)
 */
class CheckAllCommand extends BaseCollectorCommand
{
    protected $signature = 'monitor:check {target? : site url or "all"}';

    protected $description = 'Run all collectors for every site (monitor:check all).';

    protected function collectorMode(): string
    {
        return 'deep';
    }
}
