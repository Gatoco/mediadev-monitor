<?php

namespace Tests\Feature;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DemoSeedTest — demo sin internet: demo:seed deja cada sitio en verde con 24 h de checks.
 */
class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_gives_each_site_a_day_of_healthy_checks_and_resets_state(): void
    {
        $wp = Site::create(['url' => 'http://a.test', 'name' => 'a.test', 'type' => 'wp', 'current_state' => 'down', 'consecutive_failures' => 2]);
        $plain = Site::create(['url' => 'http://b.test', 'name' => 'b.test', 'type' => 'non-wp', 'current_state' => 'unknown']);

        $this->artisan('demo:seed')->assertSuccessful();
        $this->artisan('demo:seed')->assertSuccessful(); // idempotent: no duplicate rows

        $this->assertSame(289, DB::table('uptime_checks')->where('site_id', $wp->id)->count());
        $this->assertSame(0, DB::table('uptime_checks')->where('status', '!=', 200)->count());
        $this->assertSame('wp-full', $wp->fresh()->current_state->value);
        $this->assertSame(0, $wp->fresh()->consecutive_failures);
        $this->assertSame('non-wp', $plain->fresh()->current_state->value);
        $this->assertSame(1, DB::table('version_snapshots')->count()); // only the WP site has a core version
    }

    public function test_status_json_reports_seeded_uptime(): void
    {
        Site::create(['url' => 'http://a.test', 'name' => 'a.test', 'type' => 'wp', 'current_state' => 'unknown']);
        $this->artisan('demo:seed');

        $this->getJson('/status.json')
            ->assertJsonPath('sites.0.state', 'wp-full')
            ->assertJsonPath('sites.0.uptime_24h', 100);
    }
}
