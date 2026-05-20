<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Receipts the authenticated client can see — anything where they are the
 * counterparty. Returns metadata; the app fetches the PDF via the
 * existing web route /receipts/{id} which is itself behind auth.
 */
class ReceiptController extends Controller
{
    public function index(Request $request)
    {
        $clientId = (int) $request->user()->id;
        $perPage  = (int) $request->input('per_page', 25);

        $rows = DB::table('receipts')
            ->where('counterparty_type', 'client')
            ->where('counterparty_id', $clientId)
            ->whereNull('voided_at')
            ->orderByDesc('id')
            ->select([
                'id', 'series_letter', 'series_number',
                'kind', 'amount', 'currency',
                'source_table', 'source_id', 'transaction_number',
                'issued_at', 'issued_by_user_name',
            ])
            ->paginate($perPage);

        // Synthesize a display-friendly receipt number from the
        // (series_letter, series_number) pair. Keeps the API stable if the
        // backing schema later collapses these into one column.
        $rows->getCollection()->transform(function ($r) {
            $r->receipt_number = trim(($r->series_letter ?? '') . (string) ($r->series_number ?? ''));
            return $r;
        });

        return response()->json($rows);
    }
}
