<?php

/**
 * Mediadev Monitor — Activity: últimas publicaciones (solo sitios WP).
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
        $auth = $this->basicAuth($site);
        $endpoint = rtrim($site->url, '/') . '/wp-json/wp/v2/posts?per_page=' . $limit;

        $response = $this->client->get($endpoint, $auth);

        if ($response->status === 403 || $response->status === 404 || $response->status === 0) {
            $this->persist($site, [], true);
            return ['posts' => [], 'unavailable' => true];
        }

        $posts = $response->json();
        $normalized = array_map(fn (array $p) => [
            'title' => $p['title']['rendered'] ?? '(sin título)',
            'link' => $p['link'] ?? null,
            'date' => $p['date'] ?? null,
        ], $posts);

        $this->persist($site, $normalized, false);

        return ['posts' => $normalized, 'unavailable' => false];
    }

    private function basicAuth(Site $site): ?string
    {
        if ($site->apToken === null) {
            return null;
        }
        return 'application_password_user:' . $site->apToken;
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
