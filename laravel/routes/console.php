<?php

use App\Console\Commands\CollectorDeepCommand;
use App\Console\Commands\CollectorUptimeCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Replaces the vanilla crontab (uptime every 5 min, deep every 6 h).
// Single cron line: * * * * * cd /app/laravel && php artisan schedule:run
Schedule::command(CollectorUptimeCommand::class)->everyFiveMinutes();
Schedule::command(CollectorDeepCommand::class)->everySixHours();
