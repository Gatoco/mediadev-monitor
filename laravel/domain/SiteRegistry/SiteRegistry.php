<?php

/**
 * Mediadev Monitor — SiteRegistry: registro de sitios.
 */

declare(strict_types=1);

namespace Domain\SiteRegistry;

use Domain\Port\SiteRepository;

final class SiteRegistry
{
    public function __construct(private SiteRepository $repository)
    {
    }

    /** Sincroniza config/sites.php con la tabla sites (upsert por URL). */
    public function syncFromConfig(array $sites): void
    {
        $this->repository->syncFromConfig($sites);
    }

    /** @return Site[] */
    public function all(): array
    {
        return $this->repository->all();
    }

    public function find(int $id): ?Site
    {
        return $this->repository->find($id);
    }

    public function findByUrl(string $url): ?Site
    {
        return $this->repository->findByUrl($url);
    }

    public function setState(int $id, SiteState $state, int $consecutiveFailures): void
    {
        $this->repository->setState($id, $state, $consecutiveFailures);
    }
}
