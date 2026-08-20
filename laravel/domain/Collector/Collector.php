<?php

/**
 * Mediadev Monitor — Collector: orquesta las recolecciones por modo.
 *
 * modos: uptime (5 min) | deep (6h: degradación + versiones + salud + actividad)
 * La elegibilidad de cada collector depende del estado clasificado (tabla del spec).
 */

declare(strict_types=1);

namespace Domain\Collector;

use Domain\Activity\ActivityCollector;
use Domain\Degradation\Degradation;
use Domain\Infra\RestClient;
use Domain\Port\ActivitySnapshotRepository;
use Domain\Port\SiteHealthSnapshotRepository;
use Domain\Port\UptimeCheckRepository;
use Domain\Port\VersionSnapshotRepository;
use Domain\SiteHealth\SiteHealthCollector;
use Domain\SiteRegistry\Site;
use Domain\SiteRegistry\SiteRegistry;
use Domain\SiteRegistry\SiteState;
use Domain\Uptime\UptimeChecker;
use Domain\Version\VersionTracker;

final class Collector
{
    private RestClient $client;
    private SiteRegistry $registry;
    private UptimeCheckRepository $uptimeRepo;
    private VersionSnapshotRepository $versionRepo;
    private SiteHealthSnapshotRepository $healthRepo;
    private ActivitySnapshotRepository $activityRepo;
    private string $cacheDir;

    public function __construct(
        SiteRegistry $registry,
        RestClient $client,
        UptimeCheckRepository $uptimeRepo,
        VersionSnapshotRepository $versionRepo,
        SiteHealthSnapshotRepository $healthRepo,
        ActivitySnapshotRepository $activityRepo,
        string $cacheDir,
    ) {
        $this->registry = $registry;
        $this->client = $client;
        $this->uptimeRepo = $uptimeRepo;
        $this->versionRepo = $versionRepo;
        $this->healthRepo = $healthRepo;
        $this->activityRepo = $activityRepo;
        $this->cacheDir = $cacheDir;
    }

    public function runAll(string $mode): CollectionReport
    {
        $reports = [];
        foreach ($this->registry->all() as $site) {
            $reports[] = $this->runOne($site, $mode);
        }
        return new CollectionReport($reports);
    }

    public function runOne(Site $site, string $mode = 'deep'): SiteReport
    {
        $state = $site->state;

        if ($mode === 'uptime') {
            $checker = new UptimeChecker($this->client, $this->uptimeRepo);
            $result = $checker->check($site);
            $state = $checker->applyThreshold($site, $result);
            return new SiteReport($site, $state, ['uptime' => $result]);
        }

        // Modo deep: clasificar primero (Enfoque B)
        $degradation = new Degradation($this->client, $this->registry);
        $state = $degradation->classify($site);

        $metrics = [];

        // Tabla de eligibilidad (spec degradation-handling)
        if ($state === SiteState::WP_FULL || $state === SiteState::WP_DEGRADED) {
            $metrics['versions'] = (new VersionTracker($this->client, $this->versionRepo, $this->cacheDir))
                ->collect($site);
            $metrics['health'] = (new SiteHealthCollector($this->client, $this->healthRepo))->collect($site);
            $metrics['activity'] = (new ActivityCollector($this->client, $this->activityRepo))->collect($site);

            // Si algún endpoint falló con 403/404 → marcar degradado
            $endpointsOk = !$metrics['health']['unavailable'] && !$metrics['activity']['unavailable'];
            if ($state === SiteState::WP_FULL && !$endpointsOk) {
                $degradation->markDegraded($site);
                $state = SiteState::WP_DEGRADED;
            }
        }

        return new SiteReport($site, $state, $metrics);
    }
}
