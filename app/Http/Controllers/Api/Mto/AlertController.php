<?php

namespace App\Http\Controllers\Api\Mto;

use App\Http\Controllers\Controller;
use App\Models\MtoAlert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $mtoId = $request->user()->mto_profile_id;

        $alerts = MtoAlert::with(['change.actionItems'])
            ->where('mto_id', $mtoId)
            ->orderBy('alerted_at', 'desc')
            ->paginate(20);

        return response()->json($alerts->through(fn($a) => [
            'id'             => $a->id,
            'title'          => $a->change->title,
            'severity'       => $a->change->severity,
            'change_type'    => $a->change->change_type,
            'summary'        => $a->change->plain_english_summary,
            'alerted_at'     => $a->alerted_at,
            'deadline'       => $a->change->deadline,
            'actions_total'  => $a->change->actionItems->count(),
            'viewed'         => !is_null($a->dashboard_viewed_at),
        ]));
    }

    public function show(Request $request, $id)
    {
        $mtoId = $request->user()->mto_profile_id;

        $alert = MtoAlert::with(['change.actionItems', 'actionProgress'])
            ->where('mto_id', $mtoId)
            ->findOrFail($id);

        // Mark as viewed
        if (is_null($alert->dashboard_viewed_at)) {
            $alert->update(['dashboard_viewed_at' => now()]);
        }

        $progressMap = $alert->actionProgress->keyBy('action_item_id');

        return response()->json([
            'id'         => $alert->id,
            'alerted_at' => $alert->alerted_at,
            'change'     => [
                'id'                     => $alert->change->id,
                'title'                  => $alert->change->title,
                'severity'               => $alert->change->severity,
                'change_type'            => $alert->change->change_type,
                'plain_english_summary'  => $alert->change->plain_english_summary,
                'affected_jurisdictions' => $alert->change->affected_jurisdictions,
                'affected_corridors'     => $alert->change->affected_corridors,
                'effective_date'         => $alert->change->effective_date,
                'deadline'               => $alert->change->deadline,
                'source_reference'       => $alert->change->source_reference,
                'source_url'             => $alert->change->source_url,
            ],
            'actions' => $alert->change->actionItems->map(fn($item) => [
                'id'           => $item->id,
                'order'        => $item->action_order,
                'text'         => $item->action_text,
                'category'     => $item->category,
                'is_required'  => $item->is_required,
                'deadline_days'=> $item->deadline_days,
                'status'       => $progressMap[$item->id]->status ?? 'pending',
                'progress_id'  => $progressMap[$item->id]->id ?? null,
                'completed_at' => $progressMap[$item->id]->completed_at ?? null,
                'notes'        => $progressMap[$item->id]->notes ?? null,
            ]),
            'overall_progress' => [
                'total'     => $alert->change->actionItems->count(),
                'completed' => $progressMap->where('status', 'completed')->count(),
            ],
        ]);
    }
}
