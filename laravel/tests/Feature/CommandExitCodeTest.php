<?php

namespace Tests\Feature;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exit-code contract for the artisan commands (AC-04..AC-07, EV-02..EV-05):
 * 0 = OK, 1 = critical (DOWN or RED severity), 2 = usage/config error.
 *
 * Runs the REAL Collector against the in-memory DB with deterministic sites:
 * a `non-wp` site never touches the network (exit 0), an unreachable `auto`
 * site degrades to DOWN (exit 1). No mocking — full stack integration.
 */
class CommandExitCodeTest extends TestCase
{
    use RefreshDatabase;

    private function seedSite(string $url, string $type, string $state = 'unknown'): Site
    {
        return Site::create([
            'url' => $url,
            'name' => $url,
            'type' => $type,
            'wp_user' => null,
            'ap_token' => null,
            'consecutive_failures' => 0,
            'current_state' => $state,
        ]);
    }

    public function test_collector_uptime_exits_zero_with_non_wp_site(): void
    {
        // non-wp sites never touch the network in deep mode; uptime probes
        // 127.0.0.1:1 which is instantly refused → failures=1, state stays
        // unknown → not critical.
        $this->seedSite('http://127.0.0.1:1', 'non-wp');

        $this->artisan('collector:uptime')->assertExitCode(0);
    }

    public function test_collector_uptime_exits_one_when_site_is_down(): void
    {
        // A site already in DOWN state stays critical on this run.
        $this->seedSite('http://127.0.0.1:1', 'auto', 'down');

        $this->artisan('collector:uptime')->assertExitCode(1);
    }

    public function test_collector_deep_exits_one_when_site_is_down(): void
    {
        // auto type with unreachable URL → Degradation classifies DOWN.
        $this->seedSite('http://127.0.0.1:1', 'auto', 'down');

        $this->artisan('collector:deep')->assertExitCode(1);
    }

    public function test_collector_deep_exits_zero_without_criticals(): void
    {
        $this->seedSite('http://127.0.0.1:1', 'non-wp');

        $this->artisan('collector:deep')->assertExitCode(0);
    }

    public function test_monitor_check_exits_zero_without_criticals(): void
    {
        $this->seedSite('http://127.0.0.1:1', 'non-wp');

        $this->artisan('monitor:check', ['target' => 'all'])->assertExitCode(0);
    }

    public function test_monitor_check_exits_one_when_critical(): void
    {
        $this->seedSite('http://127.0.0.1:1', 'auto', 'down');

        $this->artisan('monitor:check', ['target' => 'all'])->assertExitCode(1);
    }

    public function test_monitor_check_exits_two_on_unknown_target(): void
    {
        $this->artisan('monitor:check', ['target' => 'not-a-real-target'])->assertExitCode(2);
    }

    public function test_monitor_list_exits_zero(): void
    {
        $this->seedSite('http://127.0.0.1:1', 'non-wp');

        $this->artisan('monitor:check --list')->assertExitCode(0);
    }
}
