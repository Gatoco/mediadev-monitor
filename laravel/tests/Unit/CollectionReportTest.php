<?php

namespace Tests\Unit;

use Domain\Collector\CollectionReport;
use Domain\Collector\SiteReport;
use Domain\SiteRegistry\Site;
use Domain\SiteRegistry\SiteState;
use PHPUnit\Framework\TestCase;

class CollectionReportTest extends TestCase
{
    public function test_has_critical_returns_true_for_down_state(): void
    {
        $report = new CollectionReport([
            new SiteReport(
                new Site(1, 'http://example.com', 'example', 'auto', null, null, 0, SiteState::DOWN),
                SiteState::DOWN
            ),
        ]);

        $this->assertTrue($report->hasCritical());
    }

    public function test_has_critical_returns_true_for_red_severity(): void
    {
        $report = new CollectionReport([
            new SiteReport(
                new Site(1, 'http://example.com', 'example', 'wp', null, null, 0, SiteState::WP_FULL),
                SiteState::WP_FULL,
                ['versions' => ['severity' => 'red']]
            ),
        ]);

        $this->assertTrue($report->hasCritical());
    }

    public function test_has_critical_returns_false_for_clean(): void
    {
        $report = new CollectionReport([
            new SiteReport(
                new Site(1, 'http://example.com', 'example', 'wp', null, null, 0, SiteState::WP_FULL),
                SiteState::WP_FULL,
                ['versions' => ['severity' => 'green']]
            ),
            new SiteReport(
                new Site(2, 'http://example2.com', 'example2', 'non-wp', null, null, 0, SiteState::NON_WP),
                SiteState::NON_WP
            ),
        ]);

        $this->assertFalse($report->hasCritical());
    }

    public function test_has_critical_returns_false_for_empty_report(): void
    {
        $report = new CollectionReport([]);
        $this->assertFalse($report->hasCritical());
    }
}
