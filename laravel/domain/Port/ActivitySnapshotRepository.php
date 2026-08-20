<?php

declare(strict_types=1);

namespace Domain\Port;

interface ActivitySnapshotRepository
{
    public function save(int $siteId, array $posts, bool $unavailable): void;
}
