<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegulatorySource;
use App\Models\ScraperHealth;

class HealthController extends Controller
{
    public function index()
    {
        $sources = RegulatorySource::with(['latestHealth'])
            ->orderBy('name')
            ->get()
            ->map(fn($s) => [
                'id'               => $s->id,
                'name'             => $s->name,
                'type'             => $s->type,
                'jurisdiction'     => $s->jurisdiction,
                'last_checked_at'  => $s->last_checked_at,
                'last_changed_at'  => $s->last_changed_at,
                'last_status'      => $s->last_status,
                'is_active'        => $s->is_active,
                'latest_health'    => $s->latestHealth ? [
                    'status'            => $s->latestHealth->status,
                    'run_at'            => $s->latestHealth->run_at,
                    'run_duration_ms'   => $s->latestHealth->run_duration_ms,
                    'records_fetched'   => $s->latestHealth->records_fetched,
                    'changes_detected'  => $s->latestHealth->changes_detected,
                    'error_message'     => $s->latestHealth->error_message,
                ] : null,
            ]);

        return response()->json([
            'sources'      => $sources,
            'summary'      => [
                'total'   => $sources->count(),
                'healthy' => $sources->where('last_status', 'ok')->count(),
                'failed'  => $sources->where('last_status', 'failed')->count(),
                'warning' => $sources->where('last_status', 'warning')->count(),
            ],
        ]);
    }

    public function show($sourceId)
    {
        $source  = RegulatorySource::findOrFail($sourceId);
        $history = ScraperHealth::where('source_id', $sourceId)
            ->orderBy('run_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'source'  => $source,
            'history' => $history,
        ]);
    }
}
