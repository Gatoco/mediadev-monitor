<?php
/**
 * Mediadev Monitor — web/login.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use MediadevMonitor\Auth\Auth;
use MediadevMonitor\Infra\Config;

$config = new Config();
$auth = new Auth($config);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($auth->attempt($username, $password)) {
        header('Location: index.php');
        exit;
    }
    $error = 'Credenciales inválidas';
}

$auth->startSession();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mediadev Monitor — Login</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0;
               display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #1e293b; padding: 2.5rem; border-radius: 12px; width: 320px;
                box-shadow: 0 10px 30px rgba(0,0,0,.4); }
        h1 { font-size: 1.3rem; margin: 0 0 1.5rem; text-align: center; }
        input { width: 100%; padding: .6rem; margin-bottom: .8rem; border-radius: 6px;
                border: 1px solid #334155; background: #0f172a; color: #e2e8f0; box-sizing: border-box; }
        button { width: 100%; padding: .6rem; border: 0; border-radius: 6px;
                 background: #06b6d4; color: #fff; font-weight: 600; cursor: pointer; }
        .error { color: #f87171; font-size: .85rem; margin-bottom: .8rem; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔭 Mediadev Monitor</h1>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Usuario" required autofocus>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
