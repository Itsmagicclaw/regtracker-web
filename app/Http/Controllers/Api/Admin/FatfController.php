<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDetectedChange;
use App\Models\DetectedChange;
use App\Models\RegulatorySource;
use App\Models\SanctionsEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FatfController extends Controller
{
    public function index()
    {
        return response()->json([
            'greylist' => SanctionsEntry::where('list_source', 'fatf')
                ->where('entry_type', 'country')
                ->whereNull('date_removed')
                ->where('is_active', true)
                ->orderBy('primary_name')
                ->get(['id', 'primary_name', 'reason', 'date_added']),
            'blacklist' => SanctionsEntry::where('list_source', 'fatf_blacklist')
                ->where('entry_type', 'country')
                ->whereNull('date_removed')
                ->where('is_active', true)
                ->orderBy('primary_name')
                ->get(['id', 'primary_name', 'reason', 'date_added']),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'greylist'              => 'required|array',
            'greylist.*.name'       => 'required|string',
            'greylist.*.reason'     => 'nullable|string',
            'blacklist'             => 'required|array',
            'blacklist.*.name'      => 'required|string',
            'blacklist.*.reason'    => 'nullable|string',
            'plenary_date'          => 'required|date',
            'notes'                 => 'nullable|string',
        ]);

        DB::transaction(function () use ($data) {
            // Mark all existing FATF entries as removed
            SanctionsEntry::whereIn('list_source', ['fatf', 'fatf_blacklist'])
                ->whereNull('date_removed')
                ->update(['date_removed' => now()->toDateString(), 'is_active' => false]);

            $addedGrey  = [];
            $addedBlack = [];

            foreach ($data['greylist'] as $country) {
                SanctionsEntry::create([
                    'list_source'  => 'fatf',
                    'entry_type'   => 'country',
                    'primary_name' => $country['name'],
                    'reason'       => $country['reason'] ?? 'FATF Increased Monitoring',
                    'date_added'   => $data['plenary_date'],
                    'is_active'    => true,
                ]);
                $addedGrey[] = $country['name'];
            }

            foreach ($data['blacklist'] as $country) {
                SanctionsEntry::create([
                    'list_source'  => 'fatf_blacklist',
                    'entry_type'   => 'country',
                    'primary_name' => $country['name'],
                    'reason'       => $country['reason'] ?? 'FATF Call for Action',
                    'date_added'   => $data['plenary_date'],
                    'is_active'    => true,
                ]);
                $addedBlack[] = $country['name'];
            }

            // Create detected change for grey list if anything changed
            if (!empty($addedGrey)) {
                $source = RegulatorySource::where('name', 'FATF')->first();
                $change = DetectedChange::create([
                    'source_id'              => $source?->id,
                    'detected_at'            => now(),
                    'change_type'            => 'fatf_greylist',
                    'severity'               => 'high',
                    'title'                  => 'FATF Grey List Updated — ' . $data['plenary_date'],
                    'plain_english_summary'  => 'FATF updated the grey list (increased monitoring). Countries: ' . implode(', ', $addedGrey),
                    'affected_jurisdictions' => $addedGrey,
                    'affected_corridors'     => [],
                    'effective_date'         => $data['plenary_date'],
                    'source_reference'       => 'FATF Plenary ' . $data['plenary_date'],
                    'source_url'             => 'https://www.fatf-gafi.org',
                    'qa_status'              => 'admin_approved',
                    'approved_by'            => 0,
                    'approved_at'            => now(),
                ]);
                dispatch(new ProcessDetectedChange($change));
            }

            if (!empty($addedBlack)) {
                $source = RegulatorySource::where('name', 'FATF')->first();
                $change = DetectedChange::create([
                    'source_id'              => $source?->id,
                    'detected_at'            => now(),
                    'change_type'            => 'fatf_blacklist',
                    'severity'               => 'critical',
                    'title'                  => 'FATF Black List Updated — ' . $data['plenary_date'],
                    'plain_english_summary'  => 'FATF Call for Action. Countries: ' . implode(', ', $addedBlack),
                    'affected_jurisdictions' => $addedBlack,
                    'affected_corridors'     => [],
                    'effective_date'         => $data['plenary_date'],
                    'source_reference'       => 'FATF Plenary ' . $data['plenary_date'],
                    'source_url'             => 'https://www.fatf-gafi.org',
                    'qa_status'              => 'admin_approved',
                    'approved_by'            => 0,
                    'approved_at'            => now(),
                ]);
                dispatch(new ProcessDetectedChange($change));
            }
        });

        return response()->json(['message' => 'FATF lists updated and alerts dispatched']);
    }
}
