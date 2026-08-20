<?php

namespace App\Repositories;

use App\Models\SiteHealthSnapshot;
use Domain\Port\SiteHealthSnapshotRepository;

class EloquentSiteHealthSnapshotRepository implements SiteHealthSnapshotRepository
{
    public function __construct(private SiteHealthSnapshot $model)
    {
    }

    public function save(int $siteId, ?int $score, array $tests, bool $unavailable): void
    {
        $this->model->create([
            'site_id' => $siteId,
            'tests_json' => json_encode(['tests' => $tests, 'unavailable' => $unavailable]),
            'score' => $score,
        ]);
    }
}
