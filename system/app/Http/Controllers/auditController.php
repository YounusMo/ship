<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Read-only viewer for the audit_log table.
 *
 * Admin-only. The chkAuthAdmin middleware now restricts admin/branch_admin,
 * but the audit log is a head-office tool — only `admin` should see it,
 * branch_admin should not be able to see who reviewed THEIR actions.
 */
class auditController extends Controller
{
    private function assertAdminOnly(): void
    {
        if (!in_array(auth()->user()->type, ['admin'], true)) {
            abort(403, 'Unauthorized');
        }
    }

    public function load(Request $request)
    {
        $this->assertAdminOnly();

        try {
            $q = DB::table('audit_log');

            if ($request->action) {
                $q = $q->where('action', $request->action);
            }
            if ($request->user_id) {
                $q = $q->where('user_id', (int) $request->user_id);
            }
            if ($request->target_table) {
                $q = $q->where('target_table', $request->target_table);
            }
            if ($request->target_id) {
                $q = $q->where('target_id', (int) $request->target_id);
            }
            if ($request->from) {
                $q = $q->where('created_at', '>=', $request->from . ' 00:00:00');
            }
            if ($request->to) {
                $q = $q->where('created_at', '<=', $request->to . ' 23:59:59');
            }

            $q = $q->orderBy('id', 'DESC');
            $get = $q->paginate((int) env('PAGEVIEW', 50));

            // Pre-load user names so the table doesn't N+1 lookup.
            $userIds = $get->pluck('user_id')->filter()->unique()->all();
            $users = $userIds
                ? DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id')
                : collect();

            $actions = DB::table('audit_log')->select('action')->distinct()->orderBy('action')->pluck('action');
            $tables  = DB::table('audit_log')->select('target_table')->distinct()->orderBy('target_table')->pluck('target_table');

            return view('pages.audit.table', compact('get', 'users', 'actions', 'tables'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), ['exception' => $th]);
            return response()->json(['type' => 'error'], 500);
        }
    }
}
