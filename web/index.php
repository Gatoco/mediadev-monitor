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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mediadev Monitor — Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="view-index">
    <header>
        <h1>🔭 Mediadev Monitor</h1>
        <a href="logout.php">Cerrar sesión</a>
    </header>
    <main>
        <?php
        $down = count(array_filter($sites, fn ($s) => $s['state'] === 'down'));
        $degraded = count(array_filter($sites, fn ($s) => $s['state'] === 'wp-degraded'));
        ?>
        <div class="stats">
            <div class="stat"><b><?= count($sites) ?></b>sitios</div>
            <div class="stat"><b style="color:#ef4444"><?= $down ?></b>caídos</div>
            <div class="stat"><b style="color:#f59e0b"><?= $degraded ?></b>degradados</div>
        </div>
        <table>
            <thead>
                <tr><th>Estado</th><th>Sitio</th><th>Tipo</th><th>Último check</th><th>Respuesta</th><th>Severidad</th></tr>
            </thead>
            <tbody>
                <?php foreach ($sites as $site): ?>
                <tr>
                    <td><span class="dot <?= $site['semaphore'] ?>"></span><?= htmlspecialchars($site['state']) ?></td>
                    <td><a class="site" href="site.php?id=<?= $site['id'] ?>"><?= htmlspecialchars($site['name']) ?></a></td>
                    <td><?= htmlspecialchars($site['type']) ?></td>
                    <td><?= htmlspecialchars($site['last_uptime']['ts'] ?? '—') ?></td>
                    <td><?= $site['last_uptime'] ? 'HTTP ' . $site['last_uptime']['status'] . ' · ' . $site['last_uptime']['response_ms'] . 'ms' : '—' ?></td>
                    <td><?= htmlspecialchars($site['last_severity'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
