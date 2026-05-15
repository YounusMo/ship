<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\settingsController;
use App\Http\Controllers\langController;
use App\Http\Controllers\dataController;
use Mpdf\Mpdf;

class receiptsController extends Controller
{
    /**
     * Render a receipt as a PDF, downloadable inline.
     * Route: GET /receipts/{id}
     */
    public function show($id)
    {
        $receipt = DB::table('receipts')->where('id', $id)->first();
        if (!$receipt) {
            abort(404);
        }

        // Admin can see any receipt; branch_admin only their own branch's.
        $user = auth()->user();
        if ($user->type === 'branch_admin' && $receipt->branch_id && (int) $receipt->branch_id !== (int) $user->branch) {
            abort(403);
        }

        $settings = (new settingsController())->get();
        $lang     = new langController();
        $data     = new dataController();

        $branch = $receipt->branch_id
            ? DB::table('branches')->where('id', $receipt->branch_id)->first()
            : null;

        $html = view('pages.receipts.receipt_pdf', compact('receipt', 'settings', 'lang', 'data', 'branch'))->render();

        $isRtl = ($user->lang ?? 'en') === 'ar';

        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A5-L',         // landscape A5 — receipt-sized
            'default_font'   => 'dejavusans',
            'directionality' => $isRtl ? 'rtl' : 'ltr',
            'margin_top'     => 8,
            'margin_bottom'  => 8,
            'margin_left'    => 10,
            'margin_right'   => 10,
        ]);
        $mpdf->WriteHTML($html);

        $filename = 'receipt-' . $receipt->series_letter . str_pad($receipt->series_number, 6, '0', STR_PAD_LEFT) . '.pdf';
        return response($mpdf->Output($filename, 'I'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /**
     * Find the receipt for a given source transaction and 302 redirect.
     * Lets the UI link by transaction id without knowing the receipt id.
     * Route: GET /receipts/for/{source_table}/{source_id}
     */
    public function forTransaction($source_table, $source_id)
    {
        $allowed = ['clients_transactions', 'suppliers_transactions',
                    'customs_brokers_transactions', 'branches_transactions'];
        if (!in_array($source_table, $allowed, true)) {
            abort(422, 'Invalid source table');
        }

        $r = DB::table('receipts')
            ->where('source_table', $source_table)
            ->where('source_id', $source_id)
            ->orderBy('id', 'desc')
            ->first();

        if (!$r) {
            abort(404, 'No receipt issued for this transaction');
        }

        return redirect('/receipts/' . $r->id);
    }

    /**
     * Mark a receipt as voided. Admin only. Audit-logged.
     * Route: POST /receipts/{id}/void  body: { reason }
     */
    public function void(Request $request, $id)
    {
        if (auth()->user()->type !== 'admin') {
            abort(403);
        }

        $receipt = DB::table('receipts')->where('id', $id)->first();
        if (!$receipt) {
            abort(404);
        }
        if ($receipt->voided) {
            return response()->json(['type' => 'already_voided'], 200);
        }

        $reason = mb_substr((string) $request->reason, 0, 191);
        DB::table('receipts')->where('id', $id)->update([
            'voided'           => true,
            'voided_by_user_id'=> auth()->user()->id,
            'voided_at'        => date('Y-m-d H:i:s'),
            'void_reason'      => $reason,
        ]);

        $this->logAudit(
            'receipt_void',
            'receipts',
            $id,
            [
                'series'  => $receipt->series_letter . '-' . $receipt->series_number,
                'kind'    => $receipt->kind,
                'amount'  => $receipt->amount,
                'currency'=> $receipt->currency,
                'reason'  => $reason,
            ],
            'Voided receipt'
        );

        return response()->json(['type' => 'success'], 200);
    }
}
