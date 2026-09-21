<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Último evento notificado (dedupe) por sitio y tipo.
 * fingerprint identifica UNIVOCAMENTE el snapshot que disparó el evento;
 * solo se notifica la primera vez que aparece esa firma.
 */
class NotificationState extends Model
{
    protected $table = 'notification_states';

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    protected $fillable = [
        'site_id',
        'kind',
        'fingerprint',
        'notified_at',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
