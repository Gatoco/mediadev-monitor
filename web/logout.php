<?php
/**
 * Mediadev Monitor — web/logout.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/security.php';

use MediadevMonitor\Auth\Auth;
use MediadevMonitor\Infra\Config;

send_security_headers();

$auth = new Auth(new Config());
$auth->logout();
header('Location: login.php');
exit;
