<?php

/**
 * Mediadev Monitor — Version: versiones core/plugins/themes + updates pendientes.
 *
 * Severidad: core desactualizado = red; plugins/temas = yellow; al día = green.
 * Última versión estable de WP: api.wordpress.org cacheado 24h + fallback hardcoded.
 */

declare(strict_types=1);

namespace Domain\Version;

use Domain\Infra\RestClient;
use Domain\Port\VersionSnapshotRepository;
use Domain\SiteRegistry\Site;

final class VersionTracker
{
    private const FALLBACK_WP_VERSION = '6.6.2';
    private const CACHE_FILE = 'wp-latest-version.cache.json';
    private const CACHE_TTL = 86400; // 24h
    // Ruta secundaria en la imagen (no sombreada por el volumen de datos).
    // Permite inyectar la última versión estable durante el build E2E (OQ3)
    // sin depender del volumen mediadev-data. La raíz del proyecto es /app
    // dentro del contenedor monitor, y <repo-root> en local.
    private string $imageCacheFile;

    public function __construct(
        private RestClient $client,
        private VersionSnapshotRepository $versionRepo,
        private string $cacheDir,
    ) {
        // Cache embebido en la imagen en <root>/wp-latest-version.cache.json.
        // La raíz del proyecto se deriva de la ubicación de esta clase
        // (domain/Version/VersionTracker.php → proyecto/): /app en el contenedor
        // monitor, <repo-root> en local. Independiente del cwd.
        $this->imageCacheFile = dirname(__DIR__, 2) . '/wp-latest-version.cache.json';
    }

    public function collect(Site $site): array
    {
        $auth = $site->basicAuth();
        $base = rtrim($site->url, '/');

        // La versión de WP core NO está en /wp-json/. La inferimos del HTML del
        // home: <meta name="generator" content="WordPress X.Y.Z">. Fallbacks:
        // header X-Powered-By (a menudo deshabilitado por hardening) y Link rel.
        $coreVersion = $this->detectCoreVersion($base, $auth);

        $plugins = $this->client->get($base . '/wp-json/wp/v2/plugins?per_page=100', $auth)->json();
        $themes = $this->client->get($base . '/wp-json/wp/v2/themes?per_page=100', $auth)->json();

        $severity = $this->assess($coreVersion, $plugins, $themes);

        $this->versionRepo->save($site->id, $coreVersion, $plugins, $themes, $severity);

        return ['severity' => $severity, 'core' => $coreVersion];
    }

    /**
     * Detecta la versión de WP core desde el HTML del home.
     * Busca en este orden:
     *  1. Header X-Powered-By "WordPress/X.Y.Z" (caso ideal, a menudo deshabilitado)
     *  2. <meta name="generator" content="WordPress X.Y.Z"> en el <head> del home
     *  3. Feed RSS /feed/ — tag <generator>https://wordpress.org/?v=X.Y.Z</generator>
     *
     * Devuelve null si no se puede determinar (el sitio oculta la versión, práctica
     * común de hardening). En ese caso assess() no puede marcar core desactualizado.
     */
    public function detectCoreVersion(string $base, ?string $auth): ?string
    {
        $response = $this->client->get($base . '/', $auth);

        // 1. Header X-Powered-By (caso ideal)
        foreach ($response->headers as $name => $value) {
            if (strcasecmp($name, 'X-Powered-By') === 0 && preg_match('#WordPress/\s*([\d.]+)#i', $value, $m)) {
                return $m[1];
            }
        }

        // 2. <meta name="generator"> en el HTML (caso más común)
        if ($response->status >= 200 && $response->status < 300) {
            $html = $response->body;
            // Solo scrapear el <head> para no consumir todo el body
            $headEnd = stripos($html, '</head>');
            $head = $headEnd !== false ? substr($html, 0, $headEnd) : $html;
            if (preg_match('#<meta[^>]+name\s*=\s*["\']generator["\'][^>]+content\s*=\s*["\']([^"\']+)["\']#i', $head, $m)) {
                if (preg_match('#WordPress\s+([\d.]+)#i', $m[1], $v)) {
                    return $v[1];
                }
            }
        }

        // 3. Feed RSS — tag <generator>https://wordpress.org/?v=X.Y.Z</generator>
        $feed = $this->client->get($base . '/feed/', $auth);
        if ($feed->status >= 200 && $feed->status < 300) {
            // El feed es XML; buscar el tag <generator> con atributo url o texto
            if (preg_match('#<generator[^>]*>\s*(https?://wordpress\.org/\?v=)?([\d.]+)\s*</generator>#i', $feed->body, $m)) {
                return $m[2];
            }
        }

        return null;
    }

    /** Evalúa severidad: core rojo, plugins/temas amarillo, todo al día verde. */
    public function assess(?string $coreVersion, array $plugins, array $themes): string
    {
        $latest = $this->latestStableWp();

        if ($coreVersion !== null && $coreVersion !== $latest) {
            return 'red';
        }

        $pendingPlugins = array_filter($plugins, fn ($p) => !empty($p['update'] ?? null));
        $pendingThemes = array_filter($themes, fn ($t) => !empty($t['update'] ?? null));

        if ($pendingPlugins !== [] || $pendingThemes !== []) {
            return 'yellow';
        }

        return 'green';
    }

    /** api.wordpress.org cacheado 24h, fallback a constante. */
    public function latestStableWp(): string
    {
        $cacheFile = $this->cacheDir . '/' . self::CACHE_FILE;

        if (is_file($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['version']) && $cached['fetched_at'] > time() - self::CACHE_TTL) {
                return $cached['version'];
            }
        }

        // OQ3: si el cache de datos no existe (o está vencido), probar el cache
        // embebido en la imagen. Permite inyectar la versión estable en E2E.
        if (is_file($this->imageCacheFile)) {
            $cached = json_decode((string) file_get_contents($this->imageCacheFile), true);
            if (is_array($cached) && isset($cached['version']) && $cached['fetched_at'] > time() - self::CACHE_TTL) {
                return $cached['version'];
            }
        }

        $response = $this->client->get('https://api.wordpress.org/core/version-stable/1.0/');
        $version = trim($response->body);

        if (preg_match('/^\d+\.\d+(\.\d+)?$/', $version)) {
            if (!is_dir($this->cacheDir)) {
                mkdir($this->cacheDir, 0775, true);
            }
            file_put_contents($cacheFile, json_encode([
                'version' => $version,
                'fetched_at' => time(),
            ]));
            return $version;
        }

        return self::FALLBACK_WP_VERSION;
    }
}
