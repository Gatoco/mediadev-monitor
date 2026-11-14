<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Una falla provocada desde el Chaos Lab y lo que el monitor hizo con ella. */
class ChaosExperiment extends Model
{
    protected $table = 'chaos_experiments';

    protected $casts = [
        'targets' => 'array',
        'injected_at' => 'datetime',
        'detected_at' => 'datetime',
        'alerted_at' => 'datetime',
        'restored_at' => 'datetime',
        'recovered_at' => 'datetime',
    ];

    protected $fillable = [
        'scenario',
        'target_type',
        'targets',
        'fault',
        'expected',
        'injected_at',
        'detected_at',
        'alerted_at',
        'restored_at',
        'recovered_at',
        'status',
        'result',
    ];

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }
}
