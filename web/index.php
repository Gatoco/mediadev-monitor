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
$rank = fn (array $s) => $s['semaphore'] === 'red' ? 0 : ($s['semaphore'] === 'yellow' ? 1 : 2);
usort($sites, fn ($a, $b) => [$rank($a), $a['name']] <=> [$rank($b), $b['name']]);

$counts = ['red' => 0, 'yellow' => 0, 'green' => 0];
$lastCheck = null;
foreach ($sites as $s) {
    $counts[$s['semaphore']]++;
    if (($s['last_uptime']['ts'] ?? null) > ($lastCheck ?? '')) {
        $lastCheck = $s['last_uptime']['ts'];
    }
}

$filter = $_GET['filter'] ?? 'all';
$filter = in_array($filter, ['all', 'red', 'yellow', 'green'], true) ? $filter : 'all';
$visible = $filter === 'all' ? $sites : array_values(array_filter($sites, fn ($s) => $s['semaphore'] === $filter));

$pageTitle = 'Dashboard';
$activeFilter = $filter;
$autoRefresh = true;
require __DIR__ . '/layout.php';
?>
        <?php if ($visible === []): ?>
            <p class="empty">No hay sitios en este estado.</p>
        <?php endif; ?>

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
    </main>
</div>
</body>
</html>
