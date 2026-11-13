<?php

namespace Tests\Unit;

use App\Support\Uptime\UptimeHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UptimeHistoryTest extends TestCase
{
    public function test_is_up_accepts_2xx_3xx_and_rejects_rest(): void
    {
        $this->assertTrue(UptimeHistory::isUp(200));
        $this->assertTrue(UptimeHistory::isUp(301));
        $this->assertTrue(UptimeHistory::isUp(399));
        $this->assertFalse(UptimeHistory::isUp(500));
        $this->assertFalse(UptimeHistory::isUp(null));
    }

    public function test_percentage_rounds_and_handles_empty(): void
    {
        $this->assertNull(UptimeHistory::percentage(collect()));

        $checks = collect([
            (object) ['status' => 200],
            (object) ['status' => 200],
            (object) ['status' => 500],
            (object) ['status' => 200],
        ]);
        $this->assertSame(75.0, UptimeHistory::percentage($checks));
    }

    public static function percentageProvider(): array
    {
        return [
            'all up' => [[200, 200], 100.0],
            'all down' => [[500, 503], 0.0],
            'mixed' => [[200, 500], 50.0],
        ];
    }

    #[DataProvider('percentageProvider')]
    public function test_percentage_cases(array $statuses, float $expected): void
    {
        $checks = collect($statuses)->map(fn ($s) => (object) ['status' => $s]);
        $this->assertSame($expected, UptimeHistory::percentage($checks));
    }

    public function test_sparkline_builds_24_hourly_buckets_ascending(): void
    {
        $now = CarbonImmutable::create(2026, 8, 31, 14, 30);

        $checks = collect([
            (object) ['ts' => $now->subMinutes(10), 'status' => 200, 'response_ms' => 100],   // bucket 14:00
            (object) ['ts' => $now->subMinutes(20), 'status' => 500, 'response_ms' => 200],   // bucket 14:00
            (object) ['ts' => $now->subHours(2), 'status' => 200, 'response_ms' => 300],      // bucket 12:00
        ]);

        $buckets = UptimeHistory::sparkline($checks, 24, $now);

        $this->assertCount(24, $buckets);
        // ASC cronológico: primer bucket = -23h desde las 15:00 (startOfHour de 15:30-23h = 16:00 del día anterior)
        $this->assertTrue($buckets[0]['hour']->lt($buckets[23]['hour']));
        $this->assertEquals($now->startOfHour(), $buckets[23]['hour']);

        $last = $buckets[23];
        $this->assertSame(2, $last['total']);
        $this->assertSame(1, $last['up']);
        $this->assertSame(150, $last['avg_ms']); // (100+200)/2

        $h12 = $buckets[21]; // 12:00
        $this->assertSame(1, $h12['total']);
        $this->assertSame(1, $h12['up']);
        $this->assertSame(300, $h12['avg_ms']);

        // bucket sin checks
        $this->assertSame(0, $buckets[0]['total']);
        $this->assertNull($buckets[0]['avg_ms']);
    }

    public function test_sparkline_ignores_checks_outside_window(): void
    {
        $now = CarbonImmutable::create(2026, 8, 31, 14, 0);

        $checks = collect([
            (object) ['ts' => $now->subHours(30), 'status' => 200, 'response_ms' => 100],
        ]);

        $buckets = UptimeHistory::sparkline($checks, 24, $now);

        $this->assertSame(0, array_sum(array_column($buckets, 'total')));
    }

    private function mk(int $status): object
    {
        return (object) ['status' => $status];
    }
}