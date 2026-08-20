<?php

declare(strict_types=1);

namespace Domain\Port;

interface VersionSnapshotRepository
{
    public function save(int $siteId, ?string $core, array $plugins, array $themes, string $severity): void;
}
