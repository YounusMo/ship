<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
}
