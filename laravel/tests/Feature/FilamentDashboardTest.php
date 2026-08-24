<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\UptimeCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Filament dashboard smoke tests (FD-01..FD-05):
 * - login page renders
 * - authenticated list page renders with eager-loading (no N+1 at 28 sites)
 * - dashboard renders with widgets
 */
class FilamentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function seedSites(int $count = 28): void
    {
        for ($i = 0; $i < $count; $i++) {
            $site = Site::create([
                'url' => "http://site-{$i}.test",
                'name' => "Site {$i}",
                'type' => 'non-wp',
                'wp_user' => null,
                'ap_token' => null,
                'consecutive_failures' => 0,
                'current_state' => 'wp-full',
            ]);

            UptimeCheck::create([
                'site_id' => $site->id,
                'status' => 200,
                'response_ms' => 100,
            ]);
        }
    }

    private function actingAsAdmin(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.dev',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);
    }

    public function test_login_page_renders(): void
    {
        $this->get('/admin/login')->assertStatus(200);
    }

    public function test_sites_list_renders_without_n_plus_1(): void
    {
        $this->actingAsAdmin();
        $this->seedSites();

        DB::enableQueryLog();

        $response = $this->get('/admin/sites');
        $response->assertStatus(200);

        $queries = count(DB::getQueryLog());

        // FD-05: 28 sites MUST NOT trigger N+1. Budget: sites + 4 latest
        // relations + session/auth overhead.
        $this->assertLessThanOrEqual(10, $queries, "Expected <=10 queries for 28 sites, got {$queries}");
    }

    public function test_dashboard_renders(): void
    {
        $this->actingAsAdmin();
        $this->seedSites(3);

        $this->get('/admin')->assertStatus(200);
    }
}
