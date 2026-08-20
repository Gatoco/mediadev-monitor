<?php

namespace Tests\Unit;

use Domain\Degradation\Degradation;
use Domain\Infra\RestClient;
use Domain\Port\SiteRepository;
use Domain\SiteRegistry\Site;
use Domain\SiteRegistry\SiteRegistry;
use Domain\SiteRegistry\SiteState;
use PHPUnit\Framework\TestCase;

class DegradationTest extends TestCase
{
    public function test_classify_forces_wp_when_type_is_wp(): void
    {
        $repo = $this->createMock(SiteRepository::class);
        $repo->expects($this->once())
            ->method('setState')
            ->with(1, SiteState::WP_FULL, 0);

        $registry = new SiteRegistry($repo);
        $client = new RestClient();
        $degradation = new Degradation($client, $registry);

        $site = new Site(1, 'http://example.com', 'example', 'wp', null, null, 0, SiteState::UNKNOWN);
        $state = $degradation->classify($site);

        $this->assertSame(SiteState::WP_FULL, $state);
    }

    public function test_classify_forces_non_wp_when_type_is_non_wp(): void
    {
        $repo = $this->createMock(SiteRepository::class);
        $repo->expects($this->once())
            ->method('setState')
            ->with(1, SiteState::NON_WP, 0);

        $registry = new SiteRegistry($repo);
        $client = new RestClient();
        $degradation = new Degradation($client, $registry);

        $site = new Site(1, 'http://example.com', 'example', 'non-wp', null, null, 0, SiteState::UNKNOWN);
        $state = $degradation->classify($site);

        $this->assertSame(SiteState::NON_WP, $state);
    }

    public function test_mark_degraded_sets_wp_degraded(): void
    {
        $repo = $this->createMock(SiteRepository::class);
        $repo->expects($this->once())
            ->method('setState')
            ->with(1, SiteState::WP_DEGRADED, 3);

        $registry = new SiteRegistry($repo);
        $client = new RestClient();
        $degradation = new Degradation($client, $registry);

        $site = new Site(1, 'http://example.com', 'example', 'wp', null, null, 3, SiteState::WP_FULL);
        $degradation->markDegraded($site);
    }
}
