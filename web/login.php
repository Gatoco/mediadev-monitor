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
    <link rel="stylesheet" href="style.css">
</head>
<body class="view-login">
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
