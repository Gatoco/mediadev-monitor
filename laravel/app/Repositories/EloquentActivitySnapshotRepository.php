<?php

namespace App\Repositories;

use App\Models\ActivitySnapshot;
use Domain\Port\ActivitySnapshotRepository;

class EloquentActivitySnapshotRepository implements ActivitySnapshotRepository
{
    public function __construct(private ActivitySnapshot $model)
    {
    }

    public function save(int $siteId, array $posts, bool $unavailable): void
    {
        $this->model->create([
            'site_id' => $siteId,
            'ts' => now(),
            'posts_json' => json_encode(['posts' => $posts, 'unavailable' => $unavailable]),
        ]);
    }
}
