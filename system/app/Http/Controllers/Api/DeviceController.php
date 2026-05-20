<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Registers / revokes a client device's push token. Upserting on token
 * (not on client+device-id) because tokens rotate independently of the
 * physical device and FCM/APNs guarantee uniqueness across the fleet.
 *
 * Revocation is soft — sets revoked_at — so we keep the row for audit and
 * can detect "this device was previously logged in by client X".
 */
class DeviceController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'platform'      => 'required|in:ios,android,web',
            'token'         => 'required|string|max:512',
            'app_version'   => 'nullable|string|max:32',
            'device_model'  => 'nullable|string|max:128',
            'os_version'    => 'nullable|string|max:32',
        ]);

        $clientId = $request->user()->id;
        $now      = now();

        // Upsert keyed on the token (which is globally unique per FCM/APNs).
        // If the same token previously belonged to a different client (rare —
        // happens when a phone changes hands), we re-bind it to the current
        // client and clear any revocation.
        DB::table('client_devices')->updateOrInsert(
            ['token' => $request->input('token')],
            [
                'client_id'    => $clientId,
                'platform'     => $request->input('platform'),
                'app_version'  => $request->input('app_version'),
                'device_model' => $request->input('device_model'),
                'os_version'   => $request->input('os_version'),
                'last_seen_at' => $now,
                'revoked_at'   => null,
                'updated_at'   => $now,
                'created_at'   => $now,
            ]
        );

        return response()->json(['type' => 'success']);
    }

    public function revoke(Request $request)
    {
        $request->validate(['token' => 'required|string|max:512']);
        DB::table('client_devices')
            ->where('client_id', $request->user()->id)
            ->where('token', $request->input('token'))
            ->update(['revoked_at' => now()]);
        return response()->json(['type' => 'success']);
    }
}
