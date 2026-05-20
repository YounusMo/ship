<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Paginated transaction history for the authenticated client. Scoped to
 * approved rows only — pending transactions are operator-internal and
 * shouldn't leak through the mobile API until they're approved.
 *
 * Returns a slim per-row payload to keep the wire small (mobile clients
 * are often on cellular). The list view in the app shows these fields
 * directly; the detail view will fetch one row by id (future endpoint).
 */
class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $clientId = (int) $request->user()->id;
        $request->validate([
            'currency' => 'nullable|in:usd,eur,den,cny',
            'from'     => 'nullable|date_format:Y-m-d',
            'to'       => 'nullable|date_format:Y-m-d',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $perPage = (int) $request->input('per_page', 25);

        $q = DB::table('clients_transactions')
            ->where('client_id', $clientId)
            ->where('status', 'approved');

        if ($currency = $request->input('currency')) {
            $q->where(function ($w) use ($currency) {
                $w->where('currency', $currency)->orWhere('to_currency', $currency);
            });
        }
        if (($from = $request->input('from')) && ($to = $request->input('to'))) {
            $q->whereBetween('created_date', [$from, $to]);
        }

        $rows = $q->orderByDesc('id')
            ->select([
                'id',
                'transaction_number',
                'auto_id',
                'type',
                'currency',
                'value',
                'to_currency',
                'transfer_value',
                'purpose',
                'notes',
                'created_date',
                'created_time',
                'branch',
            ])
            ->paginate($perPage);

        return response()->json($rows);
    }
}
