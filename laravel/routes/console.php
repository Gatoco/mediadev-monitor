<?php

use App\Console\Commands\CollectorDeepCommand;
use App\Console\Commands\CollectorUptimeCommand;
use App\Console\Commands\NotifyStatusCommand;
use App\Console\Commands\VerifyWpTokensCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reemplaza el crontab vanilla (uptime cada 5 min, deep cada 6 h).
// Con una sola línea cron el contenedor corre schedule:run.
Schedule::command(CollectorUptimeCommand::class)->everyFiveMinutes();
Schedule::command(CollectorDeepCommand::class)->everySixHours();

// Agente de notificaciones: diff eventos cada 5 min (después del uptime)
// y digest horario en punto. Las dos llamadas usan config/notifications.php.
Schedule::command(NotifyStatusCommand::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::command(NotifyStatusCommand::class)->hourly()->withoutOverlapping();

// Verificación diaria de Application Passwords WP (con alerta si alguno quedó inválido).
Schedule::command(VerifyWpTokensCommand::class, ['--notify'])->dailyAt('09:00')->withoutOverlapping();
