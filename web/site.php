<?php
/**
 * Mediadev Monitor — web/site.php (detalle por sitio, sesión protegida)
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
$id = (int) ($_GET['id'] ?? 0);
$detail = $dashboard->siteDetail($id);

if ($detail === []) {
    header('Location: index.php');
    exit;
}

$site = $detail['site'];
$version = $detail['last_version'];
$health = $detail['last_health'];
$activity = $detail['last_activity'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site['name']) ?> — Mediadev Monitor</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="view-site">
    <header>
        <h1><a href="index.php" style="text-decoration:none">🔭</a> <?= htmlspecialchars($site['name']) ?></h1>
        <a href="index.php">← Dashboard</a>
    </header>
    <main>
        <div class="card">
            <span class="dot <?= $detail['semaphore'] ?>"></span>
            <strong><?= htmlspecialchars($site['current_state']) ?></strong>
            — <a href="<?= htmlspecialchars($site['url']) ?>"><?= htmlspecialchars($site['url']) ?></a>
            <br><small>Fallos consecutivos: <?= (int) $site['consecutive_failures'] ?></small>
        </div>

        <div class="card">
            <h2>Uptime (últimos checks)</h2>
            <table>
                <thead><tr><th>Fecha</th><th>Status</th><th>Respuesta</th><th>TLS</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($detail['uptime_history'], 0, 10) as $check): ?>
                    <tr>
                        <td><?= htmlspecialchars($check['ts']) ?></td>
                        <td><?= $check['status'] ?? '—' ?></td>
                        <td><?= $check['response_ms'] ?? '—' ?> ms</td>
                        <td><?= htmlspecialchars($check['tls_state'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Versiones</h2>
            <?php if ($version): ?>
                <p>
                    Core: <strong><?= htmlspecialchars($version['core_version'] ?? '?') ?></strong>
                    · Severidad: <strong><?= htmlspecialchars($version['severity']) ?></strong>
                    · Snapshot: <?= htmlspecialchars($version['ts']) ?>
                </p>
                <?php
                $pending = json_decode($version['pending_json'] ?? '[]', true);
                if (!empty($pending['plugins']) || !empty($pending['themes'])): ?>
                    <p><strong>Updates pendientes:</strong></p>
                    <ul>
                    <?php foreach ($pending['plugins'] ?? [] as $p): ?>
                        <li>Plugin: <?= htmlspecialchars($p['plugin'] ?? '?') ?> (<?= htmlspecialchars($p['version'] ?? '?') ?>)</li>
                    <?php endforeach; ?>
                    <?php foreach ($pending['themes'] ?? [] as $t): ?>
                        <li>Tema: <?= htmlspecialchars($t['theme'] ?? '?') ?> (<?= htmlspecialchars($t['version'] ?? '?') ?>)</li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Sin updates pendientes ✅</p>
                <?php endif; ?>
            <?php else: ?>
                <p>Sin datos (ejecuta <code>collector.php deep</code>)</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Site Health</h2>
            <?php if ($health): ?>
                <p>
                    Score: <strong><?= $health['score'] ?? '—' ?></strong>
                    <?php if (($health['tests_json'] ?? '') !== ''): ?>
                        <?php $h = json_decode($health['tests_json'], true); ?>
                        <?php if ($h['unavailable'] ?? false): ?>
                            — endpoint no disponible (403/404)
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <p>Sin datos</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Actividad reciente</h2>
            <?php if ($activity): ?>
                <?php $a = json_decode($activity['posts_json'], true); ?>
                <?php if ($a['unavailable'] ?? false): ?>
                    <p>Endpoint no disponible (403/404)</p>
                <?php elseif (empty($a['posts'])): ?>
                    <p>Sin publicaciones recientes</p>
                <?php else: ?>
                    <ul>
                    <?php foreach ($a['posts'] as $post): ?>
                        <li><a href="<?= htmlspecialchars($post['link'] ?? '#') ?>"><?= htmlspecialchars($post['title']) ?></a> — <?= htmlspecialchars($post['date'] ?? '') ?></li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php else: ?>
                <p>Sin datos</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
