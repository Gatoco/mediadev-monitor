<?php

declare(strict_types=1);

namespace Domain\Collector;

use Domain\SiteRegistry\SiteState;

final class CollectionReport
{
    /** @param SiteReport[] $sites */
    public function __construct(public readonly array $sites)
    {
    }

    public function hasCritical(): bool
    {
        foreach ($this->sites as $report) {
            // DOWN siempre es crítico.
            if ($report->state === SiteState::DOWN) {
                return true;
            }
            // RED-critical: un sitio cuyo core está desactualizado (severity red)
            // es crítico (EV-03: down o outdated-RED → exit 1).
            if (($report->metrics['versions']['severity'] ?? null) === 'red') {
                return true;
            }
        }
        return false;
    }
}
