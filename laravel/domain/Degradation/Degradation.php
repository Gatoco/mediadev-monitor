<?php

/**
 * Mediadev Monitor — Degradation: detección automática WP vs no-WP (Enfoque B).
 *
 * Prueba /wp-json en cada sitio. 200 → WP. 404 → non-WP (si online) o DOWN (si 000).
 * type forzado en config salta la detección. Retry 2x con backoff.
 */

declare(strict_types=1);

namespace Domain\Degradation;

use Domain\Infra\RestClient;
use Domain\SiteRegistry\Site;
use Domain\SiteRegistry\SiteRegistry;
use Domain\SiteRegistry\SiteState;

final class Degradation
{
    public function __construct(
        private RestClient $client,
        private SiteRegistry $registry,
    ) {
    }

    public function classify(Site $site): SiteState
    {
        // Tipo forzado: salta detección
        if ($site->type === 'wp') {
            $this->registry->setState($site->id, SiteState::WP_FULL, $site->consecutiveFailures);
            return SiteState::WP_FULL;
        }
        if ($site->type === 'non-wp') {
            $this->registry->setState($site->id, SiteState::NON_WP, $site->consecutiveFailures);
            return SiteState::NON_WP;
        }

        // Detección automática (type = auto)
        $response = $this->client->get(rtrim($site->url, '/') . '/wp-json/');

        $state = match (true) {
            $response->status === 0   => SiteState::DOWN,
            $response->status === 200 => SiteState::WP_FULL,
            default                   => SiteState::NON_WP,
        };

        $this->registry->setState($site->id, $state, $site->consecutiveFailures);
        return $state;
    }

    /** Un sitio WP puede degradarse si endpoints específicos fallan (403/404). */
    public function markDegraded(Site $site): void
    {
        $this->registry->setState($site->id, SiteState::WP_DEGRADED, $site->consecutiveFailures);
    }
}
