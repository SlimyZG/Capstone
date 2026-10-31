<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatic 75% follow-up and resolution-deadline escalation (config/sla.php).
// Requires the Laravel scheduler to be running: `php artisan schedule:work`
// (development) or `php artisan schedule:run` every minute via Windows Task
// Scheduler / cron (deployment).
Schedule::command('concerns:process-sla')
    ->cron(config('sla.schedule_cron', '*/15 * * * *'))
    ->withoutOverlapping(30);
