<?php

declare(strict_types=1);

namespace Domain\Port;

interface SiteHealthSnapshotRepository
{
    public function save(int $siteId, ?int $score, array $tests, bool $unavailable): void;
}
