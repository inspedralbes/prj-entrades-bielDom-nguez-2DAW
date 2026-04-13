<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$dailyAt = (string) Config::get('services.ticketmaster.sync_daily_at', '04:15');
Schedule::command('ticketmaster:sync-events')->dailyAt($dailyAt);
