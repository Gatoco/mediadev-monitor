<?php
/**
 * Mediadev Monitor — MU-plugin: forzar soporte de Application Passwords en E2E.
 *
 * WP 5.6+ desactiva APs por defecto cuando el siteurl no es HTTPS. En el stack
 * E2E usamos HTTP interno (http://wp-full:80, etc.), así que forzamos el
 * soporte para que wp-cli pueda crear APs y el monitor pueda usarlas con
 * Basic Auth.
 */

declare(strict_types=1);

// WP 5.9+: wp_is_application_passwords_supported() es directa (is_ssl() ||
// environment local) y NO aplica filtro. El filtro que sí se consulta en la
// cadena de autenticación es wp_is_application_passwords_available; forzarlo
// a true habilita las APs en el stack HTTP interno del E2E.
add_filter('wp_is_application_passwords_available', '__return_true', 1);
// Compatibilidad WP < 5.9 (el filtro viejo es no-op en WP 7.x, inofensivo).
add_filter('wp_is_application_passwords_supported', '__return_true', 1);
