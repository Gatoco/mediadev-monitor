<?php

/**
 * Mediadev Monitor — Uptime: HTTP check + umbral 3 fallos consecutivos + TLS.
 */

declare(strict_types=1);

namespace MediadevMonitor\Uptime;

use MediadevMonitor\Infra\RestClient;
use MediadevMonitor\Infra\Sqlite;
use MediadevMonitor\SiteRegistry\Site;
use MediadevMonitor\SiteRegistry\SiteRegistry;
use MediadevMonitor\SiteRegistry\SiteState;
use PDO;

final class UptimeResult
{
    public function __construct(
        public readonly int $status,      // 0 = connection error (HTTP 000)
        public readonly ?int $responseMs,
        public readonly ?string $tlsState, // valid | expiring | expired | null
    ) {
    }

    public function succeeded(): bool
    {
        return $this->status !== 0;
    }
}

final class UptimeChecker
{
    public const THRESHOLD = 3;

    private PDO $pdo;

    public function __construct(
        private RestClient $client,
        private SiteRegistry $registry,
        Sqlite $sqlite,
    ) {
        $this->pdo = $sqlite->pdo();
    }

    public function check(Site $site): UptimeResult
    {
        $start = hrtime(true);
        $response = $this->client->get($site->url);
        $elapsedMs = (int) ((hrtime(true) - $start) / 1_000_000);

        $tlsState = $this->tlsState($site->url);

        return new UptimeResult(
            status: $response->status,
            responseMs: $elapsedMs,
            tlsState: $tlsState,
        );
    }

    /** Aplica el umbral 3-strikes y persiste el check. Devuelve el estado resultante. */
    public function applyThreshold(Site $site, UptimeResult $result): SiteState
    {
        $failures = $result->succeeded() ? 0 : $site->consecutiveFailures + 1;
        $state = $result->succeeded()
            ? SiteState::UNKNOWN // el estado real lo define Degradation en deep; uptime solo marca down
            : ($failures >= self::THRESHOLD ? SiteState::DOWN : $site->state);

        // Si está down por uptime y se recupera, volver a unknown (Degradation re-clasifica en deep)
        if ($result->succeeded() && $site->state === SiteState::DOWN) {
            $state = SiteState::UNKNOWN;
        }

        $this->registry->setState($site->id, $state, $failures);
        $this->persist($site, $result);

        return $state;
    }

    private function persist(Site $site, UptimeResult $result): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO uptime_checks (site_id, status, response_ms, tls_state) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $site->id,
            $result->status === 0 ? null : $result->status,
            $result->responseMs,
            $result->tlsState,
        ]);
    }

    private function tlsState(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === false || $host === null) {
            return null;
        }

        $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => true]]);
        $client = @stream_socket_client(
            'ssl://' . $host . ':443',
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($client === false) {
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);

        if (!isset($params['options']['ssl']['peer_certificate'])) {
            return null;
        }

        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        if ($cert === false || !isset($cert['validTo_time_t'])) {
            return null;
        }

        $daysLeft = (int) (($cert['validTo_time_t'] - time()) / 86400);
        if ($daysLeft < 0) {
            return 'expired';
        }
        if ($daysLeft <= 7) {
            return 'expiring';
        }
        return 'valid';
    }
}
