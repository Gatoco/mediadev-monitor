<?php

declare(strict_types=1);

namespace Domain\Port;

use Domain\SiteRegistry\Site;
use Domain\SiteRegistry\SiteState;

interface SiteRepository
{
    /** @return Site[] */
    public function all(): array;

    public function find(int $id): ?Site;

    public function findByUrl(string $url): ?Site;

    public function setState(int $id, SiteState $state, int $consecutiveFailures): void;

    /**
     * @param array<int, array{url:string, name:string, type:string, wp_user:?string, token:?string}> $sites
     */
    public function syncFromConfig(array $sites): void;
}
