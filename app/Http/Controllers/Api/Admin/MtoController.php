<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MtoProfile;
use App\Models\MtoUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MtoController extends Controller
{
    public function index()
    {
        $mtos = MtoProfile::with('mtoUsers')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($m) => $this->format($m));

        return response()->json($mtos);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'mto_name'                => 'required|string|max:255',
            'primary_contact_name'    => 'required|string|max:255',
            'primary_contact_email'   => 'required|email',
            'notification_email'      => 'required|email',
            'license_jurisdictions'   => 'required|array|min:1',
            'active_corridors'        => 'required|array|min:1',
            'license_types'           => 'required|array|min:1',
            'notification_preference' => 'sometimes|in:instant,daily_digest,both',
        ]);

        $data['created_by_admin'] = 'admin';
        $data['notification_preference'] = $data['notification_preference'] ?? 'both';

        $profile = MtoProfile::create($data);

        // Auto-create login user
        $password = Str::random(12);
        $user = MtoUser::create([
            'mto_profile_id' => $profile->id,
            'name'           => $data['primary_contact_name'],
            'email'          => $data['primary_contact_email'],
            'password'       => Hash::make($password),
            'is_active'      => true,
        ]);

        return response()->json([
            'mto'         => $this->format($profile),
            'credentials' => [
                'email'    => $user->email,
                'password' => $password,
                'note'     => 'Share these credentials with the MTO owner. Password cannot be recovered.',
            ],
        ], 201);
    }

    public function show($id)
    {
        $mto = MtoProfile::with(['mtoUsers', 'mtoAlerts.change'])->findOrFail($id);
        return response()->json($this->format($mto));
    }

    public function update(Request $request, $id)
    {
        $mto = MtoProfile::findOrFail($id);

        $data = $request->validate([
            'mto_name'                => 'sometimes|string|max:255',
            'primary_contact_name'    => 'sometimes|string|max:255',
            'primary_contact_email'   => 'sometimes|email',
            'notification_email'      => 'sometimes|email',
            'license_jurisdictions'   => 'sometimes|array',
            'active_corridors'        => 'sometimes|array',
            'license_types'           => 'sometimes|array',
            'notification_preference' => 'sometimes|in:instant,daily_digest,both',
            'is_active'               => 'sometimes|boolean',
        ]);

        $mto->update($data);

        return response()->json($this->format($mto->fresh()));
    }

    public function destroy($id)
    {
        $mto = MtoProfile::findOrFail($id);
        $mto->update(['is_active' => false]);
        MtoUser::where('mto_profile_id', $id)->update(['is_active' => false]);

        return response()->json(['message' => 'MTO deactivated successfully']);
    }

    private function format(MtoProfile $mto): array
    {
        return [
            'id'                      => $mto->id,
            'mto_name'                => $mto->mto_name,
            'primary_contact_name'    => $mto->primary_contact_name,
            'primary_contact_email'   => $mto->primary_contact_email,
            'notification_email'      => $mto->notification_email,
            'license_jurisdictions'   => $mto->license_jurisdictions,
            'active_corridors'        => $mto->active_corridors,
            'license_types'           => $mto->license_types,
            'notification_preference' => $mto->notification_preference,
            'is_active'               => $mto->is_active,
            'created_at'              => $mto->created_at,
        ];
    }
}
