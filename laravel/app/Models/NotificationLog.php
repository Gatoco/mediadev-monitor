<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una alerta emitida por notify:status (haya llegado o no a algún canal). */
class NotificationLog extends Model
{
    protected $table = 'notification_log';

    public $timestamps = false;

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered' => 'boolean',
    ];

    protected $fillable = [
        'site_id',
        'kind',
        'site_name',
        'text',
        'channels',
        'delivered',
        'sent_at',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
