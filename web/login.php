<?php
/**
 * Mediadev Monitor — web/login.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/security.php';

use MediadevMonitor\Auth\Auth;
use MediadevMonitor\Auth\RateLimit;
use MediadevMonitor\Infra\Config;

send_security_headers();

$config = new Config();
$auth = new Auth($config);
$rate = new RateLimit(dirname($config->dbPath()) . '/rate-limit.json');
$ip = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $limit = $rate->check($ip);
    if (!$limit['allowed']) {
        $error = 'Demasiados intentos. Intenta en ' . ceil($limit['retry_after'] / 60) . ' min.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($auth->attempt($username, $password)) {
            $rate->reset($ip);
            header('Location: index.php');
            exit;
        }
        $rate->recordFailure($ip);
        $error = 'Credenciales inválidas';
    }
}

$auth->startSession();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mediadev Monitor — Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="view-login">
    <div class="card">
        <h1><span class="brand-dot" aria-hidden="true"></span>Mediadev Monitor</h1>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Usuario" required autofocus>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
