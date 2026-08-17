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
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; }
        header { background: #1e293b; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        header h1 { font-size: 1.2rem; margin: 0; }
        header a { color: #94a3b8; text-decoration: none; font-size: .9rem; }
        main { padding: 2rem; max-width: 1100px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 10px; overflow: hidden; }
        th, td { padding: .8rem 1rem; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #0f172a; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; }
        tr:hover td { background: #24344d; }
        .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: .5rem; }
        .red { background: #ef4444; } .yellow { background: #f59e0b; } .green { background: #22c55e; }
        a.site { color: #38bdf8; text-decoration: none; }
        .stats { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
        .stat { background: #1e293b; border-radius: 10px; padding: 1rem 1.5rem; flex: 1; text-align: center; }
        .stat b { display: block; font-size: 1.8rem; }
    </style>
</head>
<body>
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
