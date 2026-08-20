<?php

namespace App\Repositories;

use App\Models\UptimeCheck;
use Domain\Port\UptimeCheckRepository;
use Domain\Uptime\UptimeResult;

class EloquentUptimeCheckRepository implements UptimeCheckRepository
{
    public function __construct(private UptimeCheck $model)
    {
    }

    public function save(int $siteId, UptimeResult $result): void
    {
        $this->model->create([
            'site_id' => $siteId,
            'status' => $result->status === 0 ? null : $result->status,
            'response_ms' => $result->responseMs,
            'tls_state' => $result->tlsState,
        ]);
    }
}
