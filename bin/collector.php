<?php

/**
 * Mediadev Monitor — entry point del collector (cron).
 *
 * Uso:
 *   php bin/collector.php uptime   # HTTP checks cada 5 min
 *   php bin/collector.php deep     # versiones + salud + actividad + degradación cada 6h
 *
 * Exit codes: 0 = OK, 1 = sitios caídos/críticos, 2 = error de uso/config
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use MediadevMonitor\Collector\Collector;
use MediadevMonitor\Infra\Config;

$mode = $argv[1] ?? null;

if (!in_array($mode, ['uptime', 'deep'], true)) {
    fwrite(STDERR, "Uso: php bin/collector.php <uptime|deep>\n");
    exit(2);
}

$config = new Config();
$collector = new Collector($config);
$report = $collector->runAll($mode);

// Salida básica por ahora; Reporter completo en Phase 3
foreach ($report->sites as $siteReport) {
    printf(
        "%-32s %-12s %s\n",
        $siteReport->name,
        $siteReport->state->value,
        $siteReport->state === \MediadevMonitor\SiteRegistry\SiteState::DOWN ? 'DOWN' : ''
    );
}

exit($report->hasCritical() ? 1 : 0);
