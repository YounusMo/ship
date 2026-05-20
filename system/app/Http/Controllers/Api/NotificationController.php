<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * In-app notification feed. Notifications are written by the domain
 * publishers (see app/Notifications/*) using Laravel's Notifiable trait,
 * so they end up in the `notifications` table keyed on the client.
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        $rows = $request->user()->notifications()->paginate($perPage);
        $unread = $request->user()->unreadNotifications()->count();

        return response()->json([
            'unread_count' => $unread,
            'data'         => $rows->items(),
            'current_page' => $rows->currentPage(),
            'per_page'     => $rows->perPage(),
            'total'        => $rows->total(),
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $n = $request->user()->notifications()->where('id', $id)->first();
        if (!$n) {
            abort(404);
        }
        $n->markAsRead();
        return response()->json(['type' => 'success']);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        return response()->json(['type' => 'success']);
    }

    /**
     * Per-category notification preferences. Stored on the clients row so
     * a mute applies across all the client's devices — toggling on one
     * phone should silence pushes on the other too.
     */
    public function getPrefs(Request $request)
    {
        $row = DB::table('clients')
            ->where('id', $request->user()->id)
            ->select(['notify_transactions', 'notify_shipments', 'notify_receipts'])
            ->first();
        return response()->json([
            'transactions' => (bool) ($row->notify_transactions ?? true),
            'shipments'    => (bool) ($row->notify_shipments    ?? true),
            'receipts'     => (bool) ($row->notify_receipts     ?? true),
        ]);
    }

    public function updatePrefs(Request $request)
    {
        $request->validate([
            'transactions' => 'sometimes|boolean',
            'shipments'    => 'sometimes|boolean',
            'receipts'     => 'sometimes|boolean',
        ]);
        $patch = [];
        if ($request->has('transactions')) $patch['notify_transactions'] = (bool) $request->boolean('transactions');
        if ($request->has('shipments'))    $patch['notify_shipments']    = (bool) $request->boolean('shipments');
        if ($request->has('receipts'))     $patch['notify_receipts']     = (bool) $request->boolean('receipts');

        if (!empty($patch)) {
            DB::table('clients')->where('id', $request->user()->id)->update($patch);
        }
        return $this->getPrefs($request);
    }
}
