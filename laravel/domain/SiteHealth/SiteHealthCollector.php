<?php

/**
 * Mediadev Monitor — SiteHealth: métricas via Application Passwords.
 *
 * Endpoint: /wp-json/wp-site-health/v1/tests (requiere auth).
 * Tolerancia 403/404: si el endpoint falla, se registra como unavailable
 * y el run continúa (no rompe el sitio completo).
 */

declare(strict_types=1);

namespace Domain\SiteHealth;

use Domain\Infra\RestClient;
use Domain\Port\SiteHealthSnapshotRepository;
use Domain\SiteRegistry\Site;

final class SiteHealthCollector
{
    public function __construct(
        private RestClient $client,
        private SiteHealthSnapshotRepository $healthRepo,
    ) {
    }

    /** @return array{score: ?int, tests: array, unavailable: bool} */
    public function collect(Site $site): array
    {
        $auth = $site->basicAuth();
        $endpoint = rtrim($site->url, '/') . '/wp-json/wp-site-health/v1/tests';

        $response = $this->client->get($endpoint, $auth);

        // 403/404 = endpoint no disponible (hardening o WP viejo) → registrar y continuar
        if ($response->status === 403 || $response->status === 404 || $response->status === 0) {
            $this->healthRepo->save($site->id, null, [], true);
            return ['score' => null, 'tests' => [], 'unavailable' => true];
        }

        $tests = $response->json();
        $score = $this->computeScore($tests);

        $this->healthRepo->save($site->id, $score, $tests, false);

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
}
