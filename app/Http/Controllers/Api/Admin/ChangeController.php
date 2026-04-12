<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetectedChange;
use App\Models\MtoProfile;
use App\Services\ActionBriefService;
use App\Services\MtoMatcherService;
use Illuminate\Http\Request;

class ChangeController extends Controller
{
    public function __construct(
        private ActionBriefService $actionBriefService,
        private MtoMatcherService $mtoMatcherService,
    ) {}

    public function index(Request $request)
    {
        $query = DetectedChange::with('source')
            ->orderBy('detected_at', 'desc');

        if ($request->has('qa_status')) {
            $query->where('qa_status', $request->qa_status);
        }
        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        return response()->json($query->paginate(20));
    }

    public function approve($id)
    {
        $change = DetectedChange::findOrFail($id);

        if ($change->qa_status === 'dismissed') {
            return response()->json(['error' => 'Cannot approve a dismissed change'], 422);
        }

        $change->update([
            'qa_status'   => 'admin_approved',
            'approved_by' => 0, // admin
            'approved_at' => now(),
        ]);

        // Generate action items and notify MTOs
        dispatch(new \App\Jobs\ProcessDetectedChange($change));

        return response()->json(['message' => 'Change approved and processing started']);
    }

    public function dismiss($id)
    {
        $change = DetectedChange::findOrFail($id);
        $change->update(['qa_status' => 'dismissed']);

        return response()->json(['message' => 'Change dismissed']);
    }

    public function previewAlert($mtoId, $changeId)
    {
        $mto    = MtoProfile::findOrFail($mtoId);
        $change = DetectedChange::with('actionItems')->findOrFail($changeId);

        return response()->json([
            'mto'     => $mto->only(['id', 'mto_name', 'notification_email']),
            'change'  => [
                'title'                 => $change->title,
                'severity'              => $change->severity,
                'change_type'           => $change->change_type,
                'plain_english_summary' => $change->plain_english_summary,
                'affected_jurisdictions'=> $change->affected_jurisdictions,
                'affected_corridors'    => $change->affected_corridors,
                'deadline'              => $change->deadline,
                'source_reference'      => $change->source_reference,
                'source_url'            => $change->source_url,
            ],
            'actions' => $change->actionItems->map(fn($a) => [
                'order'       => $a->action_order,
                'text'        => $a->action_text,
                'category'    => $a->category,
                'is_required' => $a->is_required,
                'deadline'    => $a->deadline_days ? "Within {$a->deadline_days} days" : null,
            ]),
        ]);
    }
}
