<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$timezone = (string) config('app.timezone', 'UTC');
Schedule::command('cloud:trash-cleanup --execute')->dailyAt('02:00')->timezone($timezone)->withoutOverlapping();
Schedule::command('cloud:cleanup-upload-staging --execute')->everySixHours()->timezone($timezone)->withoutOverlapping();
Schedule::command('cloud:cleanup-delete-staging --execute')->dailyAt('03:00')->timezone($timezone)->withoutOverlapping();
