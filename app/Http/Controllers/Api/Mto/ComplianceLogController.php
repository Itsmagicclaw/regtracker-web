<?php

namespace App\Http\Controllers\Api\Mto;

use App\Http\Controllers\Controller;
use App\Models\MtoAlert;
use Illuminate\Http\Request;

class ComplianceLogController extends Controller
{
    public function index(Request $request)
    {
        $mtoId = $request->user()->mto_profile_id;

        $query = MtoAlert::with(['change', 'actionProgress.actionItem'])
            ->where('mto_id', $mtoId)
            ->orderBy('alerted_at', 'desc');

        if ($request->has('from')) {
            $query->where('alerted_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->where('alerted_at', '<=', $request->to);
        }

        return response()->json($query->paginate(50)->through(fn($a) => $this->formatEntry($a)));
    }

    public function export(Request $request)
    {
        $mtoId   = $request->user()->mto_profile_id;
        $profile = $request->user()->mtoProfile;

        $query = MtoAlert::with(['change.actionItems', 'actionProgress.actionItem'])
            ->where('mto_id', $mtoId)
            ->orderBy('alerted_at', 'desc');

        if ($request->has('from')) {
            $query->where('alerted_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->where('alerted_at', '<=', $request->to);
        }

        $entries = $query->get()->map(fn($a) => $this->formatEntry($a));

        return response()->json([
            'mto_name'    => $profile->mto_name,
            'export_date' => now()->toDateString(),
            'date_range'  => [
                'from' => $request->from ?? 'All time',
                'to'   => $request->to   ?? now()->toDateString(),
            ],
            'total_alerts'    => $entries->count(),
            'total_completed' => $entries->sum(fn($e) => collect($e['actions'])->where('status', 'completed')->count()),
            'entries'         => $entries,
        ]);
    }

    private function formatEntry(MtoAlert $alert): array
    {
        $progressMap = $alert->actionProgress->keyBy('action_item_id');

        return [
            'alert_id'       => $alert->id,
            'alerted_at'     => $alert->alerted_at,
            'viewed_at'      => $alert->dashboard_viewed_at,
            'change_title'   => $alert->change->title,
            'change_type'    => $alert->change->change_type,
            'severity'       => $alert->change->severity,
            'source_ref'     => $alert->change->source_reference,
            'actions' => $alert->change->actionItems->map(fn($item) => [
                'text'         => $item->action_text,
                'status'       => $progressMap[$item->id]->status ?? 'pending',
                'completed_at' => $progressMap[$item->id]->completed_at ?? null,
                'notes'        => $progressMap[$item->id]->notes ?? null,
            ]),
        ];
    }
}
