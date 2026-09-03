<?php
/**
 * Mediadev Monitor — web/layout.php (shell compartido estilo LM Studio).
 * Requiere definidas antes del include:
 *   $pageTitle    (string)
 *   $activeFilter (string: 'all'|'red'|'yellow'|'green')
 *   $counts       (array{red:int,yellow:int,green:int})
 *   $lastCheck    (?string)  — ts del check más reciente
 */

function relTime(?string $ts): string
{
    if ($ts === null || $ts === '') {
        return '—';
    }
    $parsed = strtotime($ts);
    if ($parsed === false) {
        return '—';
    }
    $diff = time() - $parsed;
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

$filters = ['all' => 'Todos', 'red' => 'Caídos', 'yellow' => 'Degradados', 'green' => 'Operativos'];
$dot = ['all' => 'muted', 'red' => 'red', 'yellow' => 'yellow', 'green' => 'green'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Mediadev Monitor</title>
    <link rel="stylesheet" href="style.css">
    <?php if ($autoRefresh ?? false): ?><meta http-equiv="refresh" content="60"><?php endif; ?>
</head>
<body class="app">
<aside class="sidebar">
    <div class="brand">
        <span class="brand-dot" aria-hidden="true"></span>
        <div>
            <div class="brand-name">Mediadev Monitor</div>
            <div class="brand-sub">Sitios registrados</div>
        </div>
    </div>
    <nav class="nav">
        <?php foreach ($filters as $key => $label): ?>
        <a class="nav-item <?= $activeFilter === $key ? 'active' : '' ?>" href="index.php<?= $key !== 'all' ? '?filter=' . $key : '' ?>">
            <span class="dot <?= $dot[$key] ?>"></span>
            <span class="nav-label"><?= $label ?></span>
            <?php if ($key === 'all'): ?>
                <span class="nav-count"><?= array_sum($counts) ?></span>
            <?php else: ?>
                <span class="nav-count"><?= $counts[$key] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
        <span class="foot-ts"><?= $lastCheck !== null ? 'Check: ' . relTime($lastCheck) : 'Sin checks' ?></span>
        <a class="foot-logout" href="logout.php">Cerrar sesión</a>
    </div>
</aside>
<div class="content">
    <header class="topbar">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <?php if ($lastCheck !== null): ?>
        <span class="topbar-ts">Actualizado <?= relTime($lastCheck) ?></span>
        <?php endif; ?>
    </header>
    <main class="page">
