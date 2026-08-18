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
];
