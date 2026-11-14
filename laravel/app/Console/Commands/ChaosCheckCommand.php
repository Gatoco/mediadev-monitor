<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * "Forzar chequeo ahora" del Chaos Lab: corre el collector y enseguida el
 * agente de notificaciones, para no esperar al próximo tick del scheduler.
 *
 * El lab lo lanza en segundo plano; el lock evita que dos clics seguidos
 * dupliquen chequeos (y adelanten artificialmente el umbral de 3 fallos).
 */
class ChaosCheckCommand extends Command
{
    protected $signature = 'chaos:check {--deep : análisis profundo (clasifica WP degradado)}';

    protected $description = 'Chequeo forzado desde el Chaos Lab (collector + notify)';

    public const LOCK = 'chaos-check';

    /** Marca "chequeo en curso" que la UI muestra mientras corre. */
    public const FLAG = 'chaos-check-requested';

    public function handle(): int
    {
        $lock = Cache::lock(self::LOCK, 600);

        if (!$lock->get()) {
            $this->warn('Ya hay un chequeo forzado en curso.');

            return 0;
        }

        try {
            Artisan::call($this->option('deep') ? 'collector:deep' : 'collector:uptime');
            Artisan::call('notify:status');
        } finally {
            $lock->release();
            Cache::forget(self::FLAG);
        }

        return 0;
    }
}
