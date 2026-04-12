<?php

namespace App\Http\Controllers\Api\Mto;

use App\Http\Controllers\Controller;
use App\Models\MtoActionProgress;
use App\Models\MtoAlert;
use Illuminate\Http\Request;

class ActionController extends Controller
{
    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,in_progress,completed,skipped',
            'notes'  => 'nullable|string|max:1000',
        ]);

        $mtoId    = $request->user()->mto_profile_id;
        $progress = MtoActionProgress::whereHas('mtoAlert', fn($q) => $q->where('mto_id', $mtoId))
            ->findOrFail($id);

        $updates = ['status' => $data['status']];
        if ($data['status'] === 'in_progress' && !$progress->started_at) {
            $updates['started_at'] = now();
        }
        if ($data['status'] === 'completed') {
            $updates['completed_at'] = now();
        }
        if (isset($data['notes'])) {
            $updates['notes'] = $data['notes'];
        }

        $progress->update($updates);

        return response()->json([
            'id'           => $progress->id,
            'status'       => $progress->status,
            'completed_at' => $progress->completed_at,
        ]);
    }
}
