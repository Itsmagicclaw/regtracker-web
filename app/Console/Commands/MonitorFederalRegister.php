<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RegulatorySource;
use App\Models\RawSnapshot;
use App\Services\DiffService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonitorFederalRegister extends Command
{
    protected $signature = 'scrape:federal-register';
    protected $description = 'Monitor US Federal Register for FinCEN and OFAC notices';

    public function __construct(private DiffService $diffService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = RegulatorySource::where('source_type', 'federal_register')->first();
        if (!$source) {
            $this->error('Federal Register source not configured');
            return 1;
        }

        try {
            $this->info('Fetching Federal Register notices...');

            // Federal Register API for FinCEN and OFAC notices
            $response = Http::withOptions(['timeout' => config('regtracker.scraper_timeout_seconds')])
                ->get('https://www.federalregister.gov/api/v1/documents', [
                    'agencies' => 'treasury-department',
                    'per_page' => 100,
                    'order' => 'newest',
                ]);

            if (!$response->successful()) {
                Log::error('Federal Register fetch failed', ['status' => $response->status()]);
                $this->error('Failed to fetch Federal Register data');
                return 1;
            }

            $content = $response->body();
            $hash = hash('sha256', $content);

            $existing = RawSnapshot::where('regulatory_source_id', $source->id)
                ->where('content_hash', $hash)
                ->first();

            if ($existing) {
                $this->info('No changes detected in Federal Register notices');
                return 0;
            }

            $snapshot = RawSnapshot::create([
                'regulatory_source_id' => $source->id,
                'raw_content' => $content,
                'content_hash' => $hash,
                'file_size_bytes' => strlen($content),
                'record_count' => substr_count($content, '"id":'),
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

            $this->info('Federal Register monitoring completed successfully');
            return 0;

        } catch (\Exception $e) {
            Log::error('Federal Register monitoring error', ['message' => $e->getMessage()]);
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
