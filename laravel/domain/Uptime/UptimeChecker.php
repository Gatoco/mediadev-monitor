<?php

/**
 * SemaOPS — Uptime: HTTP check + umbral 3 fallos consecutivos + TLS.
 */

declare(strict_types=1);

namespace Domain\Uptime;

use Domain\Infra\RestClient;
use Domain\Port\UptimeCheckRepository;
use Domain\SiteRegistry\Site;
use Domain\SiteRegistry\SiteState;

final class UptimeResult
{
    public function __construct(
        public readonly int $status,      // 0 = connection error (HTTP 000)
        public readonly ?int $responseMs,
        public readonly ?string $tlsState, // valid | expiring | expired | null
    ) {
    }

    /** Éxito = respuesta HTTP 2xx/3xx. 0 (sin respuesta) y 4xx/5xx cuentan como fallo. */
    public function succeeded(): bool
    {
        return $this->status >= 200 && $this->status < 400;
    }
}

final class UptimeChecker
{
    public const THRESHOLD = 3;

    public function __construct(
        private RestClient $client,
        private UptimeCheckRepository $uptimeRepo,
    ) {
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

    /** Fallos consecutivos tras este resultado (se reinicia con un éxito). */
    public function failuresAfter(Site $site, UptimeResult $result): int
    {
        return $result->succeeded() ? 0 : $site->consecutiveFailures + 1;
    }

    /**
     * Aplica el umbral 3-strikes y persiste el check. Devuelve el estado resultante.
     *
     * - Fallo: pasa a DOWN al llegar al umbral; antes conserva el estado actual.
     * - Éxito: si venía DOWN vuelve a UNKNOWN (el deep re-clasifica);
     *   si no, conserva la clasificación del deep (wp-full, non-wp, ...).
     */
    public function applyThreshold(Site $site, UptimeResult $result): SiteState
    {
        if ($result->succeeded()) {
            $state = $site->state === SiteState::DOWN ? SiteState::UNKNOWN : $site->state;
        } else {
            $state = $this->failuresAfter($site, $result) >= self::THRESHOLD ? SiteState::DOWN : $site->state;
        }

        $this->uptimeRepo->save($site->id, $result);

        return $state;
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
