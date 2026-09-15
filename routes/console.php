<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cumpleaños de socios: one notification per milestone of the day. Needs
// `php artisan schedule:run` every minute (cron / Windows Task Scheduler,
// see README). Without it, AppServiceProvider still triggers the same
// idempotent job on the first page load of each day.
Schedule::command('birthdays:notify')->dailyAt('07:00');
