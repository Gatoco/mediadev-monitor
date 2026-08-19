<?php

/**
 * Mediadev Monitor — Activity: últimas publicaciones (solo sitios WP).
 *
 * Q#3 tokenless-first (AC-01..AC-09):
 *   - Primer intento SIEMPRE sin auth (público).
 *   - 200 (incluido []) → disponible; NO se envía AP en el primer request.
 *   - 401/403 con AP configurado → un único retry con AP.
 *   - 404/000 → unavailable (sin retry). 5xx/429 → unavailable defensivo.
 */

declare(strict_types=1);

namespace MediadevMonitor\Activity;

use MediadevMonitor\Infra\RestClient;
use MediadevMonitor\Infra\Sqlite;
use MediadevMonitor\SiteRegistry\Site;
use PDO;

final class ActivityCollector
{
    private PDO $pdo;

    public function __construct(
        private RestClient $client,
        Sqlite $sqlite,
    ) {
        $this->pdo = $sqlite->pdo();
    }

    /** @return array{posts: array, unavailable: bool} */
    public function collect(Site $site, int $limit = 5): array
    {
        $endpoint = rtrim($site->url, '/') . '/wp-json/wp/v2/posts?per_page=' . $limit;

        // AC-01 / AC-09: primer request SIN auth (tokenless-first), aunque haya AP.
        $response = $this->client->get($endpoint, null);

        // AC-03: 401/403 con AP → un único retry con AP.
        if (($response->status === 401 || $response->status === 403) && $site->basicAuth() !== null) {
            $response = $this->client->get($endpoint, $site->basicAuth());
        }

        // AC-05/AC-07: 404, 000, 5xx, 429, o AP-reintento 401/403 → unavailable.
        if ($response->status !== 200) {
            $this->persist($site, [], true);
            return ['posts' => [], 'unavailable' => true];
        }

        $posts = $response->json();

        // AC-08: normalizar a ActivityItem (id, title, date, link; campos ausentes → null).
        $normalized = array_map(fn (array $p) => [
            'id' => $p['id'] ?? null,
            'title' => $p['title']['rendered'] ?? '(sin título)',
            'link' => $p['link'] ?? null,
            'date' => $p['date'] ?? null,
        ], $posts);

        $this->persist($site, $normalized, false);

        return ['posts' => $normalized, 'unavailable' => false];
    }

    private function persist(Site $site, array $posts, bool $unavailable): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO activity_snapshots (site_id, posts_json) VALUES (?, ?)'
        );
        $stmt->execute([
            $site->id,
            json_encode(['posts' => $posts, 'unavailable' => $unavailable]),
        ]);
    }
}
