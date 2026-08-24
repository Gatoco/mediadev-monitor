<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UptimeCheck extends Model
{
    use HasFactory;

    protected $table = 'uptime_checks';

    // Legacy schema has no created_at/updated_at on snapshot tables.
    public $timestamps = false;

    protected $casts = [
        'ts' => 'datetime',
    ];

    protected $fillable = [
        'site_id',
        'ts',
        'status',
        'response_ms',
        'tls_state',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
