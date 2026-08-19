<?php
/**
 * Mediadev Monitor — web/index.php (dashboard principal, sesión protegida)
 */
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use MediadevMonitor\Auth\Auth;
use MediadevMonitor\Dashboard\Dashboard;
use MediadevMonitor\Infra\Config;

$config = new Config();
$auth = new Auth($config);

if (!$auth->check()) {
    header('Location: login.php');
    exit;
}

$dashboard = new Dashboard($config);
$sites = $dashboard->overview();

// Orden: caídos primero, luego degradados, luego por nombre.
usort($sites, fn ($a, $b) => [$a['semaphore'] === 'red' ? 0 : 1, $a['semaphore'] === 'yellow' ? 0 : 1, $a['name']]
    <=> [$b['semaphore'] === 'red' ? 0 : 1, $b['semaphore'] === 'yellow' ? 0 : 1, $b['name']]);

$counts = ['red' => 0, 'yellow' => 0, 'green' => 0];
foreach ($sites as $s) {
    $counts[$s['semaphore']]++;
}

$filter = $_GET['filter'] ?? 'all';
$visible = $filter === 'all' ? $sites : array_values(array_filter($sites, fn ($s) => $s['semaphore'] === $filter));

/** Timestamp SQLite → "hace X min" */
function relTime(?string $ts): string
{
    if ($ts === null || $ts === '') {
        return '—';
    }
    $diff = time() - strtotime($ts);
    if ($diff < 60) {
        return 'hace <1 min';
    }
    if ($diff < 3600) {
        return 'hace ' . intdiv($diff, 60) . ' min';
    }
    if ($diff < 86400) {
        return 'hace ' . intdiv($diff, 3600) . ' h';
    }
    return 'hace ' . intdiv($diff, 86400) . ' d';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mediadev Monitor — Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <meta http-equiv="refresh" content="60">
</head>
<body class="view-index">
    <header>
        <h1>🔭 Mediadev Monitor</h1>
        <div class="header-right">
            <span class="last-update">Actualizado <?= relTime($sites[0]['last_uptime']['ts'] ?? null) ?></span>
            <a href="logout.php">Cerrar sesión</a>
        </div>
    </header>
    <main>
        <div class="stats">
            <a class="stat <?= $filter === 'all' ? 'active' : '' ?>" href="?filter=all">
                <b><?= count($sites) ?></b>sitios
            </a>
            <a class="stat stat-red <?= $filter === 'red' ? 'active' : '' ?>" href="?filter=red">
                <b><?= $counts['red'] ?></b>caídos
            </a>
            <a class="stat stat-yellow <?= $filter === 'yellow' ? 'active' : '' ?>" href="?filter=yellow">
                <b><?= $counts['yellow'] ?></b>degradados
            </a>
            <a class="stat stat-green <?= $filter === 'green' ? 'active' : '' ?>" href="?filter=green">
                <b><?= $counts['green'] ?></b>ok
            </a>
        </div>

        <div class="site-grid">
            <?php foreach ($visible as $site): ?>
            <a class="site-card" href="site.php?id=<?= $site['id'] ?>">
                <div class="card-top">
                    <span class="dot <?= $site['semaphore'] ?>"></span>
                    <span class="site-name"><?= htmlspecialchars($site['name']) ?></span>
                    <span class="state-badge <?= $site['semaphore'] ?>"><?= htmlspecialchars($site['state']) ?></span>
                </div>
                <div class="card-url"><?= htmlspecialchars($site['url']) ?></div>
                <div class="card-meta">
                    <?php if ($site['last_uptime']): ?>
                        <span class="meta-item">HTTP <?= $site['last_uptime']['status'] ?> · <?= $site['last_uptime']['response_ms'] ?>ms</span>
                        <span class="meta-item"><?= relTime($site['last_uptime']['ts']) ?></span>
                    <?php else: ?>
                        <span class="meta-item">Sin checks aún</span>
                    <?php endif; ?>
                </div>
                <div class="card-badges">
                    <?php if ($site['last_version'] !== null): ?>
                        <?php if ($site['last_version']['severity'] === 'red'): ?>
                            <span class="badge badge-red">core desactualizado</span>
                        <?php endif; ?>
                        <?php if ($site['last_version']['pending_plugins'] > 0): ?>
                            <span class="badge badge-yellow"><?= $site['last_version']['pending_plugins'] ?> plugins</span>
                        <?php endif; ?>
                        <?php if ($site['last_version']['pending_themes'] > 0): ?>
                            <span class="badge badge-yellow"><?= $site['last_version']['pending_themes'] ?> temas</span>
                        <?php endif; ?>
                        <?php if ($site['last_version']['severity'] === 'green'): ?>
                            <span class="badge badge-green">al día</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($site['health_score'] !== null): ?>
                        <span class="badge badge-health">health <?= $site['health_score'] ?>/100</span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($visible === []): ?>
            <p class="empty">No hay sitios en este estado.</p>
        <?php endif; ?>
    </main>
</body>
</html>
