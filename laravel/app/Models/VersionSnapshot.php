<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionSnapshot extends Model
{
    use HasFactory;

    protected $table = 'version_snapshots';

    // Legacy schema has no created_at/updated_at on snapshot tables.
    public $timestamps = false;

    protected $casts = [
        'ts' => 'datetime',
    ];

    protected $fillable = [
        'site_id',
        'ts',
        'core_version',
        'plugins_json',
        'themes_json',
        'pending_json',
        'severity',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
