<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RegulatorySource;
use App\Models\ScraperHealth;
use Illuminate\Support\Facades\Log;

class RunHealthCheck extends Command
{
    protected $signature = 'scraper:health-check';
    protected $description = 'Check health and status of all scraper jobs';

    public function handle(): int
    {
        try {
            $this->info('Running scraper health checks...');

            $sources = RegulatorySource::all();
            $failureThreshold = config('regtracker.health_check_failure_threshold', 3);
            $alertsTriggered = 0;

            foreach ($sources as $source) {
                $lastRun = ScraperHealth::where('regulatory_source_id', $source->id)
                    ->latest('last_run_at')
                    ->first();

                if ($lastRun) {
                    $timeoutHours = $source->check_interval_hours + 2;
                    $shouldHaveRun = now()->diffInHours($source->last_checked_at) > $timeoutHours;

                    if ($shouldHaveRun && $lastRun->status === 'failure') {
                        $this->error("Health Alert: {$source->source_type} has not successfully checked in for {$timeoutHours} hours");
                        $alertsTriggered++;
                    }

                    if ($lastRun->consecutive_failures >= $failureThreshold) {
                        $this->warn("Warning: {$source->source_type} has {$lastRun->consecutive_failures} consecutive failures");
                        Log::warning('Scraper repeated failures', [
                            'source_type' => $source->source_type,
                            'consecutive_failures' => $lastRun->consecutive_failures,
                        ]);
                    }
                }

                // Log current status
                $this->line("  {$source->source_type}: Last checked " . ($source->last_checked_at ? $source->last_checked_at->diffForHumans() : 'never'));
            }

            if ($alertsTriggered === 0) {
                $this->info('All scrapers reporting healthy status');
            }

            return 0;

        } catch (\Exception $e) {
            Log::error('Health check error', ['message' => $e->getMessage()]);
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
