<?php

/**
 * Mediadev Monitor — Collector: orquesta las recolecciones por modo.
 *
 * modos: uptime (5 min) | deep (6h: degradación + versiones + salud + actividad)
 * La elegibilidad de cada collector depende del estado clasificado (tabla del spec).
 */

declare(strict_types=1);

namespace MediadevMonitor\Collector;

use MediadevMonitor\Activity\ActivityCollector;
use MediadevMonitor\Degradation\Degradation;
use MediadevMonitor\Infra\Config;
use MediadevMonitor\Infra\RestClient;
use MediadevMonitor\Infra\Sqlite;
use MediadevMonitor\SiteHealth\SiteHealthCollector;
use MediadevMonitor\SiteRegistry\Site;
use MediadevMonitor\SiteRegistry\SiteRegistry;
use MediadevMonitor\SiteRegistry\SiteState;
use MediadevMonitor\Uptime\UptimeChecker;
use MediadevMonitor\Version\VersionTracker;

final class SiteReport
{
    public function __construct(
        public readonly Site $site,
        public readonly SiteState $state,
        public readonly array $metrics = [],
    ) {
    }
}

final class CollectionReport
{
    /** @param SiteReport[] $sites */
    public function __construct(public readonly array $sites)
    {
    }

    public function hasCritical(): bool
    {
        foreach ($this->sites as $report) {
            if ($report->state === SiteState::DOWN) {
                return true;
            }
        }
        return false;
    }
}

final class Collector
{
    private Sqlite $sqlite;
    private SiteRegistry $registry;
    private RestClient $client;

    public function __construct(private Config $config)
    {
        $this->sqlite = new Sqlite($config->dbPath());
        $this->registry = new SiteRegistry($config);
        $this->client = new RestClient();
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
            $checker = new UptimeChecker($this->client, $this->registry, $this->sqlite);
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
            $metrics['versions'] = (new VersionTracker($this->client, $this->sqlite, $this->config->dbPath() . '.cache'))
                ->collect($site);
            $metrics['health'] = (new SiteHealthCollector($this->client, $this->sqlite))->collect($site);
            $metrics['activity'] = (new ActivityCollector($this->client, $this->sqlite))->collect($site);

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
