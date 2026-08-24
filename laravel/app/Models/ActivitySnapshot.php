<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivitySnapshot extends Model
{
    use HasFactory;

    protected $table = 'activity_snapshots';

    // Legacy schema has no created_at/updated_at on snapshot tables.
    public $timestamps = false;

    protected $casts = [
        'ts' => 'datetime',
    ];

    protected $fillable = [
        'site_id',
        'ts',
        'posts_json',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
