<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\UptimeCheck;
use App\Models\VersionSnapshot;
use App\Repositories\EloquentUptimeCheckRepository;
use App\Repositories\EloquentVersionSnapshotRepository;
use Domain\Uptime\UptimeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the frozen-timestamp bug: the migration shipped
 * default(now()), evaluated once at migrate time, and the repositories
 * never set ts — so every row landed on the same 2026-09-03 minute.
 */
class UptimeCheckPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        // UptimeResult shares a file with UptimeChecker (PSR-4 violation),
        // so it is only autoloadable once that file is loaded.
        class_exists(\Domain\Uptime\UptimeChecker::class);
    }

    private function site(): Site
    {
        return Site::create(['url' => 'https://example.test', 'name' => 'example']);
    }

    public function test_uptime_check_persists_the_current_timestamp(): void
    {
        $site = $this->site();
        (new EloquentUptimeCheckRepository(new UptimeCheck()))
            ->save($site->id, new UptimeResult(200, 42, 'valid'));

        $check = UptimeCheck::where('site_id', $site->id)->sole();

        $this->assertNotNull($check->ts, 'ts must be set by the repository, not left to the column default');
        $this->assertTrue(
            $check->ts->greaterThan(now()->subMinute()),
            'ts must be the current time, not frozen at migration time'
        );
    }

    public function test_version_snapshot_persists_the_current_timestamp(): void
    {
        $site = $this->site();
        (new EloquentVersionSnapshotRepository(new VersionSnapshot()))
            ->save($site->id, '6.6.1', [], [], 'green');

        $snapshot = VersionSnapshot::where('site_id', $site->id)->sole();

        $this->assertNotNull($snapshot->ts);
        $this->assertTrue($snapshot->ts->greaterThan(now()->subMinute()));
    }
}
