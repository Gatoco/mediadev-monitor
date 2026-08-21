<?php

use App\Console\Commands\CollectorDeepCommand;
use App\Console\Commands\CollectorUptimeCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// LA-06 / LA-07: single cron line (`* * * * * cd /app/laravel && php artisan schedule:run`)
// delegates cadence here.
Schedule::command(CollectorUptimeCommand::class)->everyFiveMinutes();
Schedule::command(CollectorDeepCommand::class)->everySixHours();
