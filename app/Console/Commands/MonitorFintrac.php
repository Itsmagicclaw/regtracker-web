<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RegulatorySource;
use App\Models\RawSnapshot;
use App\Services\DiffService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonitorFintrac extends Command
{
    protected $signature = 'scrape:fintrac';
    protected $description = 'Monitor FINTRAC Guidance and Compliance Notices';

    public function __construct(private DiffService $diffService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = RegulatorySource::where('source_type', 'fintrac')->first();
        if (!$source) {
            $this->error('FINTRAC source not configured');
            return 1;
        }

        try {
            $this->info('Fetching FINTRAC Guidance...');

            // FINTRAC publishes guidance documents and notices
            $response = Http::withOptions(['timeout' => config('regtracker.scraper_timeout_seconds')])
                ->get('https://www.fintrac-gafi.gc.ca/guidance-directives/guidance/index-eng');

            if (!$response->successful()) {
                Log::error('FINTRAC fetch failed', ['status' => $response->status()]);
                $this->error('Failed to fetch FINTRAC data');
                return 1;
            }

            $content = $response->body();
            $hash = hash('sha256', $content);

            $existing = RawSnapshot::where('regulatory_source_id', $source->id)
                ->where('content_hash', $hash)
                ->first();

            if ($existing) {
                $this->info('No changes detected in FINTRAC guidance');
                return 0;
            }

            $snapshot = RawSnapshot::create([
                'regulatory_source_id' => $source->id,
                'raw_content' => $content,
                'content_hash' => $hash,
                'file_size_bytes' => strlen($content),
                'record_count' => substr_count($content, '<article'),
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

            $this->info('FINTRAC monitoring completed successfully');
            return 0;

        } catch (\Exception $e) {
            Log::error('FINTRAC monitoring error', ['message' => $e->getMessage()]);
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
