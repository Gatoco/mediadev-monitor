<?php
/**
 * Mediadev Monitor — fixture-mu.php
 *
 * MU-plugin del stack E2E que emula casos reales de MediaDev según FIXTURE_TYPE:
 *   full      → comportamiento estándar de WordPress (nada especial).
 *   outdated  → fuerza el meta generator "WordPress 6.8.8" para que
 *               VersionTracker::detectCoreVersion() lea una versión vieja.
 *   hardened  → exige Application Password en /wp/v2/* (401 sin token, 200
 *               con AP) y anula site-health (404). Emula hardening real.
 *
 * Aplicación Passwords: se fuerza vía enable-app-passwords.php para HTTP.
 * El índice /wp-json/ y /wp-json/wp/v2/posts sin token quedan 401 (para que
 * ActivityCollector ejerza el retry con AP — EV-09); el índice raíz /wp-json/
 * queda 200 para que Degradation::classify() no lo tome como non-WP.
 */

declare(strict_types=1);

$fixtureType = getenv('FIXTURE_TYPE') ?: 'full';

/* ------------------------------------------------------------------ */
/* FIXTURE: outdated — core viejo                                      */
/* ------------------------------------------------------------------ */
if ($fixtureType === 'outdated') {
    remove_action('wp_head', 'wp_generator');
    add_action('wp_head', static function (): void {
        echo '<meta name="generator" content="WordPress 6.8.8" />' . "\n";
    }, 1);
}

/* ------------------------------------------------------------------ */
/* Site-health: registrar /wp-site-health/v1/tests (raíz).             */
/* En WP actual esa ruta no existe como raíz (solo sub-rutas), así que */
/* el monitor no la encuentra. La registramos para full/outdated para  */
/* que devuelva 200 (disponible); en hardened se elimina (404).        */
/* ------------------------------------------------------------------ */
if ($fixtureType !== 'hardened') {
    add_action('rest_api_init', static function (): void {
        register_rest_route('wp-site-health/v1', '/tests', [
            'methods'             => 'GET',
            'callback'            => static function () {
                $data = [
                    'direct' => [
                        [
                            'test'    => 'rest_availability',
                            'label'   => 'REST availability',
                            'status'  => 'good',
                            'badge'   => ['label' => 'good', 'color' => 'blue'],
                            'tests'   => [],
                        ],
                    ],
                    'async'  => [],
                ];
                return new WP_REST_Response($data, 200);
            },
            'permission_callback' => '__return_true',
        ]);
    });
}

/* ------------------------------------------------------------------ */
/* FIXTURE: hardened — REST exige AP; site-health anulado              */
/* ------------------------------------------------------------------ */
if ($fixtureType === 'hardened') {
    // Anular /wp-json/wp-site-health/v1/tests → 404 (hardening).
    add_filter('rest_endpoints', static function (array $endpoints): array {
        foreach ($endpoints as $route => $handlers) {
            if (str_starts_with((string) $route, '/wp-site-health/')) {
                unset($endpoints[$route]);
            }
        }
        return $endpoints;
    });

    // Exigir credenciales en /wp/v2/* : 401 sin token, 200 con AP válido.
    // El índice raíz /wp-json/ (route '/') NO se bloquea (Degradation depende
    // de que devuelva 200 para clasificar como WP).
    add_filter('rest_authentication_errors', static function ($result) {
        if ($result instanceof WP_Error) {
            return $result;
        }

        // Autenticar las credenciales Basic (AP) explícitamente: al llegar aquí,
        // la sesión puede no estar resuelta aún (wp_get_current_user() cachea
        // false si determine_current_user corrió sin credenciales antes del
        // hook rest_authentication_errors). Validar la AP a mano garantiza que
        // un token válido obtenga 200 y uno inválido siga en 401.
        $auth_user = null;
        if (isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
            $auth_user = wp_authenticate_application_password(
                null,
                $_SERVER['PHP_AUTH_USER'],
                $_SERVER['PHP_AUTH_PW']
            );
            if ($auth_user instanceof WP_User) {
                wp_set_current_user($auth_user->ID);
            }
        }

        if (is_user_logged_in()) {
            return $result;
        }

        global $wp;
        $route = isset($wp->query_vars['rest_route']) ? (string) $wp->query_vars['rest_route'] : '';
        // /wp-json/wp/v2/* exige auth; /wp-json/ (índice) no.
        if (preg_match('#^/?wp/v2/#', $route)) {
            return new WP_Error(
                'rest_forbidden',
                'Sorry, you are not allowed to do that.',
                ['status' => 401]
            );
        }
        return $result;
    }, 999);
}
