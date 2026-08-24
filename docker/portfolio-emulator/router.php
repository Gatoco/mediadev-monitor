<?php
/**
 * Mediadev Monitor — Portfolio Emulator.
 *
 * Emula los 28 sitios del portafolio de MediaDev (scrapeados de mediadev.cl)
 * con 1 solo contenedor: responde según el Host header. Cada sitio replica el
 * comportamiento HTTP/REST real medido:
 *   - wp-json 200 + meta generator con la versión real detectada
 *   - wp/v2/posts público (tokenless) para WP; 404 para no-WP
 *   - wp/v2/plugins|themes con Basic Auth (401 sin AP, 200 con AP)
 *   - wp-site-health/v1/tests: 200 (full) o 404 (hardened: mediadev.cl)
 *   - metas ocultas: sitios que en la vida real no exponen la versión
 *
 * Los hosts "down" NO están acá: se emulan con contenedores sin listener
 * (conexión rechazada → HTTP 000 → DOWN), igual que el fixture E2E.
 */

declare(strict_types=1);

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$host = preg_replace('/:\d+$/', '', $host);

// Perfil por sitio real (verificado contra mediadev.cl, 2026-08-24).
// 'v' => versión WP real detectada, null = la oculta (meta ausente).
$profiles = [
    'diariotalca.cl'            => ['v' => '7.1',      'hardened' => false],
    'casa-humana.cl'            => ['v' => '6.8.8',    'hardened' => false],
    'agriculthor.cl'            => ['v' => null,       'hardened' => false],
    'aikidomaipukai.com'        => ['v' => '7.1',      'hardened' => false],
    'alimentate.cl'             => ['v' => '7.1',      'hardened' => false],
    'cardiomedica.cl'           => ['v' => null,       'hardened' => false, 'nonwp' => true],
    'carrosdelmaule.cl'         => ['v' => '7.1',      'hardened' => false],
    'cecinasdonjacinto.cl'      => ['v' => '7.1',      'hardened' => false],
    'conequip.cl'               => ['v' => null,       'hardened' => false],
    'estadioespanoltalca.cl'    => ['v' => '7.0.2',    'hardened' => false],
    'hotelvivomontana.cl'       => ['v' => null,       'hardened' => false],
    'hubot.cl'                  => ['v' => null,       'hardened' => false],
    'innovacion.ucm.cl'         => ['v' => '7.1',      'hardened' => false],
    'integravita.cl'            => ['v' => '7.1',      'hardened' => false],
    'itma.cl'                   => ['v' => '7.1',      'hardened' => false],
    'laboratorioaleman.cl'      => ['v' => '7.0.4',    'hardened' => false],
    'macmedica.cl'              => ['v' => '7.1',      'hardened' => false],
    'mapeko.org'                => ['v' => '7.1',      'hardened' => false],
    'meymaq.cl'                 => ['v' => '7.1',      'hardened' => false],
    'morissalud.cl'             => ['v' => '6.8.6',    'hardened' => false],
    'myfcardiosalud.cl'         => ['v' => '7.0.4',    'hardened' => false],
    'okrental.cl'               => ['v' => '7.1',      'hardened' => false],
    'radiataforestal.cl'        => ['v' => '7.1',      'hardened' => false],
    'septimaruta.cl'            => ['v' => '7.1',      'hardened' => false],
    'servivalchile.cl'          => ['v' => '7.1',      'hardened' => false],
    'villalegre.cl'             => ['v' => '7.0.4',    'hardened' => false],
    'vinadetoroalexander.cl'    => ['v' => null,       'hardened' => false, 'nonwp' => true],
    'mediadev.cl'               => ['v' => null,       'hardened' => true],
];

if (!isset($profiles[$host])) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit("404 Not Found (emulador no conoce {$host})\n");
}

$profile = $profiles[$host];
$path    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// ------------------------------------------------------------- helpers
function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
}

function require_auth(array $profile): ?array
{
    // Basic auth "monitor:token" (como las Application Passwords reales).
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Basic\s+(.+)/i', $header, $m)) {
        $decoded = base64_decode($m[1]);
        [$user, $token] = array_pad(explode(':', $decoded, 2), 2, '');
        if ($user !== '' && strlen($token) >= 10) {
            return ['user' => $user, 'token' => $token];
        }
    }
    return null;
}

// ------------------------------------------------------------- routing
if ($profile['nonwp'] ?? false) {
    // no-WP: / 200 (HTML), /wp-json/ y /wp/v2/* → 404.
    if ($path === '/') {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        echo "<!doctype html><html><head><title>{$host}</title></head><body><h1>{$host}</h1><p>Sitio no-WordPress (emulado).</p></body></html>";
        exit;
    }
    http_response_code(404);
    echo '404';
    exit;
}

// Sitios "down" (hotelvivomontana, septimaruta) NO pasan por acá:
// son contenedores sin listener (ver docker-compose.yml).

// --- / : home con meta generator (o sin él si el sitio oculta la versión)
if ($path === '/') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    $meta = $profile['v'] !== null
        ? '<meta name="generator" content="WordPress ' . $profile['v'] . '" />'
        : '';
    echo "<!doctype html><html><head><title>{$host}</title>{$meta}</head><body><h1>{$host}</h1><p>WordPress emulado (versión " . ($profile['v'] ?? 'oculta') . ").</p></body></html>";
    exit;
}

// --- /wp-json/ (índice de la API REST)
if ($path === '/wp-json/' || $path === '/wp-json' || $path === '/index.php/wp-json/') {
    json_out([
        'name' => $host,
        'url'  => "https://{$host}",
        'namespaces' => ['wp/v2', 'wp-site-health/v1'],
        'authentication' => ['application-passwords'],
    ]);
    exit;
}

// --- endpoints autenticados con AP (como WP real)
$auth = require_auth($profile);

if (str_starts_with($path, '/wp-json/wp/v2/posts')) {
    if (($profile['hardened'] ?? false) && $auth === null) {
        json_out(['code' => 'rest_forbidden', 'message' => 'Sorry, you are not allowed to do that.'], 401);
        exit;
    }
    json_out([
        ['id' => 1, 'date' => date('c'), 'slug' => 'noticia-emulada', 'title' => ['rendered' => 'Noticia de ' . $host], 'link' => "https://{$host}/noticia-emulada"],
    ]);
    exit;
}

if (str_starts_with($path, '/wp-json/wp/v2/plugins') || str_starts_with($path, '/wp-json/wp/v2/themes')) {
    if ($auth === null) {
        json_out(['401' => 'unauthorized'], 401);
        exit;
    }
    $kind = str_contains($path, 'plugins') ? 'plugin' : 'theme';
    json_out([
        ['slug' => 'muestra-' . $kind, 'status' => 'active', 'version' => '1.0.0'],
    ]);
    exit;
}

if (str_starts_with($path, '/wp-json/wp-site-health/v1/tests')) {
    if (($profile['hardened'] ?? false) && $auth === null) {
        http_response_code(404);
        echo 'Not Found';
        exit;
    }
    json_out([
        'direct' => [
            ['test' => 'rest_availability', 'status' => 'good'],
            ['test' => 'wordpress_version', 'status' => 'good'],
        ],
        'async' => [],
    ]);
    exit;
}

// cualquier otro path → 404 como WP real
http_response_code(404);
header('Content-Type: text/plain');
echo '404 Not Found';
