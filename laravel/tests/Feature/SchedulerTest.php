<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Scheduler cadence (LA-06): uptime every 5 minutes, deep every 6 hours,
 * registered through a single `schedule:run` cron entry.
 */
class SchedulerTest extends TestCase
{
    private function findEvent(string $needle): ?object
    {
        $schedule = $this->app->make(Schedule::class);

        return collect($schedule->events())->first(
            fn ($e) => str_contains($e->command, $needle)
        );
    }

    public function test_uptime_scheduled_every_five_minutes(): void
    {
        $event = $this->findEvent('collector:uptime');

        $this->assertNotNull($event, 'collector:uptime must be scheduled');
        $this->assertSame('*/5 * * * *', $event->expression);
    }

    public function test_deep_scheduled_every_six_hours(): void
    {
        $event = $this->findEvent('collector:deep');

        $this->assertNotNull($event, 'collector:deep must be scheduled');
        $this->assertSame('0 */6 * * *', $event->expression);
    }
}
