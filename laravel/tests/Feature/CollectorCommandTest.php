<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * AC-06: artisan collectors exit 2 when site configuration is missing.
 *
 * The collect:* and monitor:check commands read config/sites.php. When no
 * sites are defined the command must abort with exit code 2 (configuration
 * error) before attempting any collection or DB access.
 */
class CollectorCommandTest extends TestCase
{
    public function test_collector_uptime_exits_2_when_config_missing(): void
    {
        config(['sites.sites' => []]);

        $this->artisan('collector:uptime')->assertExitCode(2);
    }

    public function test_collector_deep_exits_2_when_config_missing(): void
    {
        config(['sites.sites' => []]);

        $this->artisan('collector:deep')->assertExitCode(2);
    }

    public function test_monitor_check_all_exits_2_when_config_missing(): void
    {
        config(['sites.sites' => []]);

        $this->artisan('monitor:check all')->assertExitCode(2);
    }
}
