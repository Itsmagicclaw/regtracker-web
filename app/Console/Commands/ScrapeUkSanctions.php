<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RegulatorySource;
use App\Models\RawSnapshot;
use App\Services\DiffService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeUkSanctions extends Command
{
    protected $signature = 'scrape:uk-sanctions';
    protected $description = 'Scrape UK Consolidated Sanctions List and detect changes';

    public function __construct(private DiffService $diffService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = RegulatorySource::where('source_type', 'uk_sanctions')->first();
        if (!$source) {
            $this->error('UK Sanctions source not configured');
            return 1;
        }

        try {
            $this->info('Fetching UK Consolidated Sanctions List...');

            // UK CSL is available as CSV and ODS formats
            $response = Http::withOptions(['timeout' => config('regtracker.scraper_timeout_seconds')])
                ->get('https://www.trade-tariff.service.gov.uk/api/v2/sanctions');

            if (!$response->successful()) {
                Log::error('UK Sanctions fetch failed', ['status' => $response->status()]);
                $this->error('Failed to fetch UK Sanctions data');
                return 1;
            }

            $content = $response->body();
            $hash = hash('sha256', $content);

            $existing = RawSnapshot::where('regulatory_source_id', $source->id)
                ->where('content_hash', $hash)
                ->first();

            if ($existing) {
                $this->info('No changes detected in UK Sanctions list');
                return 0;
            }

            $snapshot = RawSnapshot::create([
                'regulatory_source_id' => $source->id,
                'raw_content' => $content,
                'content_hash' => $hash,
                'file_size_bytes' => strlen($content),
                'record_count' => substr_count($content, "\n"),
                'captured_at' => now(),
            ]);

            $previous = RawSnapshot::where('regulatory_source_id', $source->id)
                ->where('id', '!=', $snapshot->id)
                ->latest('created_at')
                ->first();

            if ($previous) {
                $this->info('Processing diffs...');
                $this->diffService->detectChanges($source, $previous->raw_content, $content);
            }

            $source->update([
                'last_checked_at' => now(),
                'last_changed_at' => now(),
            ]);

            $this->info('UK Sanctions scrape completed successfully');
            return 0;

        } catch (\Exception $e) {
            Log::error('UK Sanctions scrape error', ['message' => $e->getMessage()]);
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
