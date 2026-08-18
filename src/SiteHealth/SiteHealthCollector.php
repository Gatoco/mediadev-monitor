<?php

/**
 * Mediadev Monitor — SiteHealth: métricas via Application Passwords.
 *
 * Endpoint: /wp-json/wp-site-health/v1/tests (requiere auth).
 * Tolerancia 403/404: si el endpoint falla, se registra como unavailable
 * y el run continúa (no rompe el sitio completo).
 */

declare(strict_types=1);

namespace MediadevMonitor\SiteHealth;

use MediadevMonitor\Infra\RestClient;
use MediadevMonitor\Infra\Sqlite;
use MediadevMonitor\SiteRegistry\Site;
use PDO;

final class SiteHealthCollector
{
    private PDO $pdo;

    public function __construct(
        private RestClient $client,
        Sqlite $sqlite,
    ) {
        $this->pdo = $sqlite->pdo();
    }

    /** @return array{score: ?int, tests: array, unavailable: bool} */
    public function collect(Site $site): array
    {
        $auth = $site->basicAuth();
        $endpoint = rtrim($site->url, '/') . '/wp-json/wp-site-health/v1/tests';

        $response = $this->client->get($endpoint, $auth);

        // 403/404 = endpoint no disponible (hardening o WP viejo) → registrar y continuar
        if ($response->status === 403 || $response->status === 404 || $response->status === 0) {
            $this->persist($site, null, [], true);
            return ['score' => null, 'tests' => [], 'unavailable' => true];
        }

        $tests = $response->json();
        $score = $this->computeScore($tests);

        $this->persist($site, $score, $tests, false);

        return ['score' => $score, 'tests' => $tests, 'unavailable' => false];
    }

    private function computeScore(array $tests): int
    {
        $total = 0;
        $passed = 0;

        foreach (['direct', 'async'] as $group) {
            foreach ($tests[$group] ?? [] as $test) {
                $total++;
                if (($test['status'] ?? '') === 'good') {
                    $passed++;
                }
            }
        }

        return $total > 0 ? (int) round(($passed / $total) * 100) : 0;
    }

    private function basicAuth(Site $site): ?string
    {
        // Deprecated: usar Site::basicAuth() directamente.
        return $site->basicAuth();
    }

    private function persist(Site $site, ?int $score, array $tests, bool $unavailable): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO site_health_snapshots (site_id, tests_json, score) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $site->id,
            json_encode(['tests' => $tests, 'unavailable' => $unavailable]),
            $score,
        ]);
    }
}
