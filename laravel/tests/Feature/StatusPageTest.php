<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\UptimeCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * StatusPageTest — página pública /status + endpoint JSON del polling:
 * - renderiza grid con datos del sitio (semáforo, ms, uptime %, sparkline)
 * - /status.json expone el mismo payload para el polling 5s
 * - sitio sin checks → uptime null (mostrado como "—", no penaliza)
 */
class StatusPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_page_renders_with_site_data(): void
    {
        $site = Site::create([
            'url' => 'http://demo.test',
            'name' => 'Demo',
            'type' => 'wp',
            'wp_user' => 'monitor',
            'ap_token' => null,
            'consecutive_failures' => 0,
            'current_state' => 'wp-full',
        ]);

        foreach ([200, 200, 500] as $status) {
            UptimeCheck::create([
                'site_id' => $site->id,
                'status' => $status,
                'response_ms' => 123,
            ]);
        }

        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('Demo')
            ->assertSee('wp-full')
            ->assertSee('123')
            ->assertSee('66.7%'); // 2/3 checks up (últimas 24h y 7d)
    }

    public function test_status_json_contains_polling_payload(): void
    {
        $site = Site::create([
            'url' => 'http://demo.test',
            'name' => 'Demo',
            'type' => 'wp',
            'wp_user' => null,
            'ap_token' => null,
            'consecutive_failures' => 2,
            'current_state' => 'down',
        ]);

        UptimeCheck::create([
            'site_id' => $site->id,
            'status' => 0,
            'response_ms' => null,
        ]);

        $response = $this->get('/status.json');
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('generated_at', $data);
        $this->assertArrayHasKey('sites', $data);

        $entry = $data['sites'][0];
        $this->assertSame('Demo', $entry['name']);
        $this->assertSame('down', $entry['state']);
        $this->assertSame('Caído', $entry['state_label']);
        $this->assertSame(2, $entry['failures']);
        $this->assertEquals(0.0, $entry['uptime_24h']);
        $this->assertNull($entry['last_ms']);
        $this->assertArrayHasKey('sparkline', $entry);
        $this->assertCount(24, $entry['sparkline']);
    }

    public function test_status_site_without_checks_shows_null_uptime(): void
    {
        Site::create([
            'url' => 'http://nuevo.test',
            'name' => 'Nuevo',
            'type' => 'auto',
            'wp_user' => null,
            'ap_token' => null,
            'consecutive_failures' => 0,
            'current_state' => 'unknown',
        ]);

        $response = $this->get('/status.json');

        $entry = collect($response->json('sites'))->firstWhere('name', 'Nuevo');
        $this->assertNull($entry['uptime_24h']);
        $this->assertNull($entry['uptime_7d']);
    }
}