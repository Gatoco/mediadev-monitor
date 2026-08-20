<?php

namespace App\Repositories;

use App\Models\Site as SiteModel;
use Domain\Port\SiteRepository;
use Domain\SiteRegistry\Site;
use Domain\SiteRegistry\SiteState;

class EloquentSiteRepository implements SiteRepository
{
    public function __construct(private SiteModel $model)
    {
    }

    /** @return Site[] */
    public function all(): array
    {
        return $this->model->query()
            ->orderBy('name')
            ->get()
            ->map(fn (SiteModel $m) => $this->toDomain($m))
            ->all();
    }

    public function find(int $id): ?Site
    {
        $m = $this->model->query()->find($id);
        return $m ? $this->toDomain($m) : null;
    }

    public function findByUrl(string $url): ?Site
    {
        $m = $this->model->query()->where('url', $url)->first();
        return $m ? $this->toDomain($m) : null;
    }

    public function setState(int $id, SiteState $state, int $consecutiveFailures): void
    {
        $this->model->query()
            ->where('id', $id)
            ->update([
                'current_state' => $state->value,
                'consecutive_failures' => $consecutiveFailures,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<int, array{url:string, name:string, type:string, wp_user:?string, token:?string}> $sites
     */
    public function syncFromConfig(array $sites): void
    {
        foreach ($sites as $site) {
            $this->model->query()->updateOrCreate(
                ['url' => $site['url']],
                [
                    'name' => $site['name'],
                    'type' => $site['type'],
                    'wp_user' => $site['wp_user'] ?? null,
                    'ap_token' => $site['token'] ?? null,
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function toDomain(SiteModel $m): Site
    {
        $state = $m->current_state;
        if (!$state instanceof SiteState) {
            $state = SiteState::tryFrom((string) $state) ?? SiteState::UNKNOWN;
        }

        return new Site(
            id: $m->id,
            url: $m->url,
            name: $m->name,
            type: $m->type,
            wpUser: $m->wp_user,
            apToken: $m->ap_token,
            consecutiveFailures: $m->consecutive_failures,
            state: $state,
        );
    }
}
