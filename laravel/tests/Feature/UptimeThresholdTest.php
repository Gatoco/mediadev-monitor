<?php

namespace Tests\Feature;

use App\Models\Site;
use Domain\Collector\Collector;
use Domain\Port\UptimeCheckRepository;
use Domain\SiteRegistry\Site as DomainSite;
use Domain\SiteRegistry\SiteState;
use Domain\Uptime\UptimeChecker;
use Domain\Uptime\UptimeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regla de 3 fallos consecutivos del uptime: un 5xx/4xx cuenta como fallo y
 * el estado + contador quedan persistidos entre corridas del collector.
 */
class UptimeThresholdTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        class_exists(UptimeChecker::class); // UptimeResult vive en el mismo archivo
    }

    public function test_only_2xx_and_3xx_count_as_success(): void
    {
        $this->assertTrue((new UptimeResult(200, 10, null))->succeeded());
        $this->assertTrue((new UptimeResult(301, 10, null))->succeeded());
        $this->assertFalse((new UptimeResult(404, 10, null))->succeeded());
        $this->assertFalse((new UptimeResult(500, 10, null))->succeeded());
        $this->assertFalse((new UptimeResult(0, 10, null))->succeeded());
    }

    public function test_threshold_transitions(): void
    {
        $repo = new class implements UptimeCheckRepository {
            public function save(int $siteId, UptimeResult $result): void
            {
            }
        };
        $checker = new UptimeChecker(new \Domain\Infra\RestClient(), $repo);
        $fail = new UptimeResult(500, 10, null);
        $ok = new UptimeResult(200, 10, null);

        $site = fn (int $failures, SiteState $state) => new DomainSite(1, 'http://x.test', 'x', 'wp', null, null, $failures, $state);

        // Por debajo del umbral conserva el estado clasificado por el deep.
        $this->assertSame(SiteState::WP_FULL, $checker->applyThreshold($site(0, SiteState::WP_FULL), $fail));
        $this->assertSame(1, $checker->failuresAfter($site(0, SiteState::WP_FULL), $fail));
        // El tercer fallo consecutivo lo marca caído.
        $this->assertSame(SiteState::DOWN, $checker->applyThreshold($site(2, SiteState::WP_FULL), $fail));
        // Un éxito no borra la clasificación de un sitio sano…
        $this->assertSame(SiteState::WP_FULL, $checker->applyThreshold($site(0, SiteState::WP_FULL), $ok));
        // …pero un caído que vuelve queda pendiente de re-clasificar.
        $this->assertSame(SiteState::UNKNOWN, $checker->applyThreshold($site(3, SiteState::DOWN), $ok));
        $this->assertSame(0, $checker->failuresAfter($site(3, SiteState::DOWN), $ok));
    }

    public function test_collector_persists_failures_until_site_is_down(): void
    {
        // Puerto cerrado: conexión rechazada → HTTP 000.
        $site = Site::create([
            'url' => 'http://127.0.0.1:9',
            'name' => 'cerrado',
            'type' => 'wp',
            'current_state' => 'wp-full',
        ]);

        $collector = app(Collector::class);

        $collector->runAll('uptime');
        $collector->runAll('uptime');
        $site->refresh();
        $this->assertSame(2, $site->consecutive_failures);
        $this->assertSame(SiteState::WP_FULL, $site->current_state);

        $collector->runAll('uptime');
        $site->refresh();
        $this->assertSame(3, $site->consecutive_failures);
        $this->assertSame(SiteState::DOWN, $site->current_state);
    }
}
