<?php

namespace App\Repositories;

use App\Models\VersionSnapshot;
use Domain\Port\VersionSnapshotRepository;

class EloquentVersionSnapshotRepository implements VersionSnapshotRepository
{
    public function __construct(private VersionSnapshot $model)
    {
    }

    public function save(int $siteId, ?string $core, array $plugins, array $themes, string $severity): void
    {
        $this->model->create([
            'site_id' => $siteId,
            'core_version' => $core,
            'plugins_json' => json_encode($plugins),
            'themes_json' => json_encode($themes),
            'pending_json' => json_encode([
                'plugins' => array_values(array_filter($plugins, fn ($p) => !empty($p['update'] ?? null))),
                'themes' => array_values(array_filter($themes, fn ($t) => !empty($t['update'] ?? null))),
            ]),
            'severity' => $severity,
        ]);
    }
}
