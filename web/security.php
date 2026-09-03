<?php

/**
 * Mediadev Monitor — web/security.php: headers de seguridad compartidos.
 * Incluir al inicio de cada página del dashboard.
 */

declare(strict_types=1);

function send_security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'");
}
