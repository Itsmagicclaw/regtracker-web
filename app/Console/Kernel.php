<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // OFAC SDN List - Run every 4 hours
        $schedule->command('scrape:ofac')
            ->everyFourHours()
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('OFAC scraper failed in scheduler');
            });

        // UK Consolidated Sanctions List - Run every 6 hours
        $schedule->command('scrape:uk-sanctions')
            ->everySixHours()
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('UK sanctions scraper failed in scheduler');
            });

        // UN Consolidated Sanctions Lists - Run every 6 hours
        $schedule->command('scrape:un-sanctions')
            ->everySixHours()
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('UN sanctions scraper failed in scheduler');
            });

        // EU EEAS Sanctions Lists - Run every 8 hours
        $schedule->command('scrape:eu-sanctions')
            ->everyEightHours()
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('EU sanctions scraper failed in scheduler');
            });

        // Australian DFAT Sanctions Lists - Run every 12 hours
        $schedule->command('scrape:dfat')
            ->twiceDaily(2, 14)
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('DFAT scraper failed in scheduler');
            });

        // AUSTRAC Sanctions and AML/CTF Lists - Run every 8 hours
        $schedule->command('scrape:austrac')
            ->everyEightHours()
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('AUSTRAC scraper failed in scheduler');
            });

        // FCA Register and Enforcement Actions - Run every 12 hours
        $schedule->command('scrape:fca')
            ->twiceDaily(3, 15)
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('FCA scraper failed in scheduler');
            });

        // FINTRAC Guidance and Compliance Notices - Run every 12 hours
        $schedule->command('scrape:fintrac')
            ->twiceDaily(4, 16)
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('FINTRAC scraper failed in scheduler');
            });

        // US Federal Register FinCEN and OFAC notices - Run every 4 hours
        $schedule->command('scrape:federal-register')
            ->everyFourHours()
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Federal Register scraper failed in scheduler');
            });

        // Health check for all scrapers - Run every 30 minutes
        $schedule->command('scraper:health-check')
            ->everyThirtyMinutes()
            ->withoutOverlapping();

        // Send daily digests to MTOs with daily preference - Run at 6 AM UTC
        $schedule->command('digest:send-daily')
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Daily digest sending failed in scheduler');
            });

        // Process detected changes queue (runs every minute to process queued jobs)
        $schedule->command('queue:work', [
            '--queue' => 'default',
            '--max-time' => 3600,
            '--max-jobs' => 1000,
        ])
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
