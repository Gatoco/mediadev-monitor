<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Services\Notification\MonitorEventDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una caída = una alerta "down" al entrar y una "recovered" al salir,
 * sin repetirse entre corridas del agente.
 */
class DownEpisodeNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function detector(): MonitorEventDetector
    {
        return new MonitorEventDetector(15, storage_path('framework/testing/notify-snapshots.json'));
    }

    /** @return array<int, string> */
    private function kinds(): array
    {
        return array_column($this->detector()->detect()['events'], 'kind');
    }

    public function test_down_then_recovered_are_notified_once_each(): void
    {
        $site = Site::create(['url' => 'http://a.test', 'name' => 'a', 'current_state' => 'wp-full']);

        $this->assertSame([], $this->kinds());

        $site->update(['current_state' => 'down', 'consecutive_failures' => 3]);
        $this->assertSame(['down'], $this->kinds());
        $this->assertSame([], $this->kinds(), 'la caída no se re-notifica mientras dure');

        $site->update(['current_state' => 'unknown', 'consecutive_failures' => 0]);
        $this->assertSame(['recovered'], $this->kinds());
        $this->assertSame([], $this->kinds());

        $site->update(['current_state' => 'down', 'consecutive_failures' => 3]);
        $this->assertSame(['down'], $this->kinds(), 'una caída nueva vuelve a avisar');
    }

    public function test_single_failure_does_not_send_recovered(): void
    {
        Site::create(['url' => 'http://b.test', 'name' => 'b', 'current_state' => 'wp-full', 'consecutive_failures' => 1]);

        $this->assertSame([], $this->kinds());
    }
}
