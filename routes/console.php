<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sanctions scrapers — every 6 hours
Schedule::command('scrape:ofac')->everysixHours();
Schedule::command('scrape:uk-sanctions')->everySixHours();
Schedule::command('scrape:un-sanctions')->everySixHours();

// Daily scrapes — 2am UTC
Schedule::command('scrape:eu-sanctions')->dailyAt('02:00');
Schedule::command('scrape:dfat')->dailyAt('02:15');
Schedule::command('monitor:austrac')->dailyAt('02:30');
Schedule::command('monitor:fca')->dailyAt('02:45');
Schedule::command('monitor:fintrac')->dailyAt('03:00');
Schedule::command('monitor:federal-register')->dailyAt('03:15');

// Health check every 6 hours
Schedule::command('health:check')->everySixHours();

// Daily digest at 7am UTC
Schedule::command('digest:send')->dailyAt('07:00');
