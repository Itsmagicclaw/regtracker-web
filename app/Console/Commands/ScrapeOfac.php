<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RegulatorySource;
use App\Models\RawSnapshot;
use App\Services\DiffService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeOfac extends Command
{
    protected $signature = 'scrape:ofac';
    protected $description = 'Scrape OFAC SDN List and detect changes';

    public function __construct(private DiffService $diffService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = RegulatorySource::where('source_type', 'ofac')->first();
        if (!$source) {
            $this->error('OFAC source not configured');
            return 1;
        }

        try {
            $this->info('Fetching OFAC SDN List...');

            // OFAC publishes SDN list in various formats; using consolidated XML
            $response = Http::withOptions(['timeout' => config('regtracker.scraper_timeout_seconds')])
                ->get('https://www.treasury.gov/ofac/downloads/sdnlist.xml');

            if (!$response->successful()) {
                Log::error('OFAC fetch failed', ['status' => $response->status()]);
                $this->error('Failed to fetch OFAC data');
                return 1;
            }

            $content = $response->body();
            $hash = hash('sha256', $content);

            // Check if content changed
            $existing = RawSnapshot::where('regulatory_source_id', $source->id)
                ->where('content_hash', $hash)
                ->first();

            if ($existing) {
                $this->info('No changes detected in OFAC data');
                return 0;
            }

            // Store new snapshot
            $snapshot = RawSnapshot::create([
                'regulatory_source_id' => $source->id,
                'raw_content' => $content,
                'content_hash' => $hash,
                'file_size_bytes' => strlen($content),
                'record_count' => substr_count($content, '<sdnEntry>'),
                'captured_at' => now(),
            ]);

            // Get previous snapshot
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

            $this->info('OFAC scrape completed successfully');
            return 0;

        } catch (\Exception $e) {
            Log::error('OFAC scrape error', ['message' => $e->getMessage()]);
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
