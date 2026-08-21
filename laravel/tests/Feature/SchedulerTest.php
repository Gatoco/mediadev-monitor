<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * LA-06: scheduler cadence contracts.
 *
 *   collector:uptime  -> everyFiveMinutes()  (5-min boundary: minute % 5 == 0)
 *   collector:deep    -> everySixHours()      (6h boundary: hour % 6 == 0, minute 0)
 *
 * We assert the real Schedule instance (registered in routes/console.php)
 * reports the commands as due at the boundaries and NOT due off-boundary.
 * This avoids executing the collectors (no network / DB dependency) while
 * proving the single cron line `schedule:run` will trigger them on time.
 */
class SchedulerTest extends TestCase
{
    /** @return array<int,string> */
    private function dueCommands(): array
    {
        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);

        return $schedule->dueEvents($this->app)
            ->map(static fn ($event) => (string) $event->command)
            ->all();
    }

    private function dueContains(string $substring): bool
    {
        return Collection::make($this->dueCommands())
            ->contains(static fn (string $command) => str_contains($command, $substring));
    }

    public function test_uptime_is_due_at_five_minute_boundary(): void
    {
        $this->travelTo(now()->startOfHour()); // minute 0 of any hour

        $this->assertTrue($this->dueContains('collector:uptime'));
    }

    public function test_uptime_is_not_due_off_boundary(): void
    {
        $this->travelTo(now()->startOfHour()->addMinutes(1)); // minute 1

        $this->assertFalse($this->dueContains('collector:uptime'));
    }

    public function test_deep_is_due_at_six_hour_boundary(): void
    {
        $this->travelTo(now()->startOfDay()); // 00:00

        $this->assertTrue($this->dueContains('collector:deep'));
    }

    public function test_deep_is_not_due_off_boundary(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(1)); // 01:00

        $this->assertFalse($this->dueContains('collector:deep'));
    }
}
