<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RegulatorySource;
use App\Models\RawSnapshot;
use App\Services\DiffService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonitorAustrac extends Command
{
    protected $signature = 'scrape:austrac';
    protected $description = 'Monitor AUSTRAC Sanctions and Compliance Lists';

    public function __construct(private DiffService $diffService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = RegulatorySource::where('source_type', 'austrac')->first();
        if (!$source) {
            $this->error('AUSTRAC source not configured');
            return 1;
        }

        try {
            $this->info('Fetching AUSTRAC Sanctions Lists...');

            // AUSTRAC publishes sanctions and AML/CTF lists
            $response = Http::withOptions(['timeout' => config('regtracker.scraper_timeout_seconds')])
                ->get('https://www.austrac.gov.au/business/compliance-and-enforcement/aml-cft-compliance');

            if (!$response->successful()) {
                Log::error('AUSTRAC fetch failed', ['status' => $response->status()]);
                $this->error('Failed to fetch AUSTRAC data');
                return 1;
            }

            $content = $response->body();
            $hash = hash('sha256', $content);

            $existing = RawSnapshot::where('regulatory_source_id', $source->id)
                ->where('content_hash', $hash)
                ->first();

            if ($existing) {
                $this->info('No changes detected in AUSTRAC lists');
                return 0;
            }

            $snapshot = RawSnapshot::create([
                'regulatory_source_id' => $source->id,
                'raw_content' => $content,
                'content_hash' => $hash,
                'file_size_bytes' => strlen($content),
                'record_count' => substr_count($content, '<div class="entity'),
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

            $this->info('AUSTRAC monitoring completed successfully');
            return 0;

        } catch (\Exception $e) {
            Log::error('AUSTRAC monitoring error', ['message' => $e->getMessage()]);
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
