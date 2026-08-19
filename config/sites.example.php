<?php

/**
 * Mediadev Monitor — registro de sitios (plantilla).
 *
 * Copia este archivo a config/sites.php y completa con los sitios reales.
 * config/sites.php está en .gitignore — NUNCA subir tokens al repositorio.
 *
 * Los Application Passwords se generan en cada sitio WordPress:
 * Usuarios → Perfil → Application Passwords (WP 5.6+)
 *
 * type: 'auto' (detección automática) | 'wp' (forzar WordPress) | 'non-wp' (forzar no-WP)
 * wp_user: nombre del usuario WP del que se generó el Application Password (necesario para Basic Auth)
 */

return [
    // Ejemplo — sitio WordPress completo
    [
        'url'   => 'https://mediadev.cl',
        'name'  => 'MediaDev',
        'type'  => 'auto',
        'wp_user' => 'admin', // usuario WP del que se generó el Application Password
        'token' => '', // Application Password (24 caracteres, sin espacios)
    ],

    // Ejemplo — sitio no-WordPress (solo uptime)
    [
        'url'   => 'https://ejemplo-no-wp.cl',
        'name'  => 'Sitio estático',
        'type'  => 'non-wp',
        'wp_user' => null,
        'token' => null,
    ],

    /* ------------------------------------------------------------------
     * Stack E2E realistic-sites-e2e
     *
     * Los siguientes 5 fixtures (red `net-sites`) se usan para verificar que
     * el framework clasifica cada caso real de MediaDev (RF-12). `bin/e2e-assert.sh`
     * genera config/sites.php automáticamente desde /ap-tokens/*.token; los tokens
     * se crean con los wp-cli oneshots (docker compose --profile e2e).
     *
     * Estado esperado por fixture:
     *   wp-full      → wp-full (site sano)
     *   wp-outdated  → wp-full + RED (core 6.8.8 < latest 7.0.4)
     *   wp-hardened  → wp-degraded (exige AP en /wp/v2/*; site-health 404)
     *   non-wp       → non-wp (estático, /wp-json/ 404)
     *   down         → down (sin listener)
     * ------------------------------------------------------------------ */
    [
        'url'   => 'http://wp-full:80',
        'name'  => 'wp-full',
        'type'  => 'wp',
        'wp_user' => 'monitor',
        'token' => '', // desde /ap-tokens/wp-full.token
    ],
    [
        'url'   => 'http://wp-outdated:80',
        'name'  => 'wp-outdated',
        'type'  => 'wp',
        'wp_user' => 'monitor',
        'token' => '', // desde /ap-tokens/wp-outdated.token
    ],
    [
        'url'   => 'http://wp-hardened:80',
        'name'  => 'wp-hardened',
        'type'  => 'auto',
        'wp_user' => 'monitor',
        'token' => '', // desde /ap-tokens/wp-hardened.token
    ],
    [
        'url'   => 'http://non-wp:80',
        'name'  => 'non-wp',
        'type'  => 'non-wp',
        'wp_user' => null,
        'token' => null,
    ],
    [
        'url'   => 'http://down:80',
        'name'  => 'down',
        'type'  => 'auto',
        'wp_user' => null,
        'token' => null,
    ],
];
