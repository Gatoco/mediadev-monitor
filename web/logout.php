<?php
/**
 * Mediadev Monitor — web/logout.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use MediadevMonitor\Auth\Auth;
use MediadevMonitor\Infra\Config;

$auth = new Auth(new Config());
$auth->logout();
header('Location: login.php');
exit;
