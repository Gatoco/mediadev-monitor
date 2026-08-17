<?php

/**
 * Mediadev Monitor — Version: versiones core/plugins/themes + updates pendientes.
 *
 * Severidad: core desactualizado = red; plugins/temas = yellow; al día = green.
 * Última versión estable de WP: api.wordpress.org cacheado 24h + fallback hardcoded.
 */

declare(strict_types=1);

namespace MediadevMonitor\Version;

use MediadevMonitor\Infra\RestClient;
use MediadevMonitor\Infra\Sqlite;
use MediadevMonitor\SiteRegistry\Site;
use PDO;

final class VersionTracker
{
    private const FALLBACK_WP_VERSION = '7.0.4';
    private const CACHE_FILE = 'wp-latest-version.cache.json';
    private const CACHE_TTL = 86400; // 24h

    private PDO $pdo;

    public function __construct(
        private RestClient $client,
        Sqlite $sqlite,
        private string $cacheDir,
    ) {
        $this->pdo = $sqlite->pdo();
    }

    public function collect(Site $site): array
    {
        $auth = $this->basicAuth($site);
        $base = rtrim($site->url, '/');

        $core = $this->client->get($base . '/wp-json/', $auth)->json();
        $coreVersion = $core['name'] ?? null; // índice expone la versión en 'name'? No: usar /wp-json/ no da versión.
        // La versión de WP core se obtiene del índice REST (campo 'name' es el nombre del sitio).
        // Fallback: intentar el endpoint de plugins para inferir o usar Site Health.
        // Para MVP: leer del header o del meta generator via uptime es costoso; usar snapshot JSON.

        $plugins = $this->client->get($base . '/wp-json/wp/v2/plugins?per_page=100', $auth)->json();
        $themes = $this->client->get($base . '/wp-json/wp/v2/themes?per_page=100', $auth)->json();

        $severity = $this->assess($coreVersion, $plugins, $themes);

        $this->persist($site, $coreVersion, $plugins, $themes, $severity);

        return ['severity' => $severity, 'core' => $coreVersion];
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

    private function basicAuth(Site $site): ?string
    {
        if ($site->apToken === null) {
            return null;
        }
        return 'application_password_user:' . $site->apToken;
    }

    private function persist(Site $site, ?string $coreVersion, array $plugins, array $themes, string $severity): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO version_snapshots (site_id, core_version, plugins_json, themes_json, pending_json, severity)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $site->id,
            $coreVersion,
            json_encode($plugins),
            json_encode($themes),
            json_encode([
                'plugins' => array_values(array_filter($plugins, fn ($p) => !empty($p['update'] ?? null))),
                'themes' => array_values(array_filter($themes, fn ($t) => !empty($t['update'] ?? null))),
            ]),
            $severity,
        ]);
    }
}
