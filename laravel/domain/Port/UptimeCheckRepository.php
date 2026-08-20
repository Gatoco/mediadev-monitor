<?php

declare(strict_types=1);

namespace Domain\Port;

use Domain\Uptime\UptimeResult;

interface UptimeCheckRepository
{
    public function save(int $siteId, UptimeResult $result): void;
}
