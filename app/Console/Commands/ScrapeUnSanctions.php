<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RegulatorySource;
use App\Models\RawSnapshot;
use App\Services\DiffService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeUnSanctions extends Command
{
    protected $signature = 'scrape:un-sanctions';
    protected $description = 'Scrape UN Consolidated Sanctions Lists and detect changes';

    public function __construct(private DiffService $diffService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = RegulatorySource::where('source_type', 'un_sanctions')->first();
        if (!$source) {
            $this->error('UN Sanctions source not configured');
            return 1;
        }

        try {
            $this->info('Fetching UN Consolidated Sanctions Lists...');

            // UN publishes consolidated list via Security Council
            $response = Http::withOptions(['timeout' => config('regtracker.scraper_timeout_seconds')])
                ->get('https://www.un.org/securitycouncil/sanctions/information');

            if (!$response->successful()) {
                Log::error('UN Sanctions fetch failed', ['status' => $response->status()]);
                $this->error('Failed to fetch UN Sanctions data');
                return 1;
            }

            $content = $response->body();
            $hash = hash('sha256', $content);

            $existing = RawSnapshot::where('regulatory_source_id', $source->id)
                ->where('content_hash', $hash)
                ->first();

            if ($existing) {
                $this->info('No changes detected in UN Sanctions list');
                return 0;
            }

            $snapshot = RawSnapshot::create([
                'regulatory_source_id' => $source->id,
                'raw_content' => $content,
                'content_hash' => $hash,
                'file_size_bytes' => strlen($content),
                'record_count' => substr_count($content, "<entry>"),
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

            $this->info('UN Sanctions scrape completed successfully');
            return 0;

        } catch (\Exception $e) {
            Log::error('UN Sanctions scrape error', ['message' => $e->getMessage()]);
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
