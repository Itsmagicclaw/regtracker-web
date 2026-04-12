<?php

namespace App\Http\Controllers\Api\Mto;

use App\Http\Controllers\Controller;
use App\Models\MtoActionProgress;
use App\Models\MtoAlert;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $mtoId = $request->user()->mto_profile_id;

        $openActions = MtoActionProgress::whereHas('mtoAlert', fn($q) => $q->where('mto_id', $mtoId))
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        $overdueActions = MtoActionProgress::whereHas('mtoAlert', fn($q) => $q->where('mto_id', $mtoId))
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('actionItem', fn($q) => $q->whereNotNull('deadline_days')
                ->whereRaw('DATE_ADD(mto_alerts.alerted_at, INTERVAL action_items.deadline_days DAY) < NOW()')
            )
            ->count();

        $recentAlerts = MtoAlert::with(['change'])
            ->where('mto_id', $mtoId)
            ->orderBy('alerted_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($a) => [
                'id'         => $a->id,
                'title'      => $a->change->title,
                'severity'   => $a->change->severity,
                'change_type'=> $a->change->change_type,
                'alerted_at' => $a->alerted_at,
                'viewed'     => !is_null($a->dashboard_viewed_at),
            ]);

        return response()->json([
            'open_actions_count'    => $openActions,
            'overdue_actions_count' => $overdueActions,
            'recent_alerts'         => $recentAlerts,
        ]);
    }
}
