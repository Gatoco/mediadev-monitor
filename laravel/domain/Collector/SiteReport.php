<?php

declare(strict_types=1);

namespace Domain\Collector;

use Domain\SiteRegistry\Site;
use Domain\SiteRegistry\SiteState;

final class SiteReport
{
    public function __construct(
        public readonly Site $site,
        public readonly SiteState $state,
        public readonly array $metrics = [],
    ) {
    }
}
