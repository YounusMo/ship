<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\dataController;

class profitsController extends Controller
{

    public function load(Request $request){
        try {

            $from = $request->from;
            $to   = $request->to;

            return view('pages.profits.table',compact('from','to'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    /**
     * Per-container P&L derived from the ledger.
     *
     *   GET /profits/container/{type}/{id}
     *     type ∈ {sky, sea}  — translated to cost_object_type internally
     *
     * Runs `journalController::activityForCostObject('container_sky'|'container_sea', $id)`
     * then groups the per-account net into revenue vs. expense using each
     * account's `type` and `normal_balance` — same convention as the system
     * P&L. The result is the ledger-truth answer to "did this container
     * make money?" — independent of the calculator in profits/table.blade.php
     * which reads the entity tables directly. Until every fee and expense is
     * cost-object-tagged, the two will diverge; that's a feature, not a bug
     * (it tells you what's not yet flowing through the ledger).
     */
    public function container(Request $request, string $type, int $id)
    {
        if (!in_array($type, ['sky', 'sea'], true)) abort(404);
        if ($id <= 0) abort(404);

        $table         = $type === 'sky' ? 'containers_sky' : 'containers_sea';
        $costObjectKey = $type === 'sky' ? 'container_sky'  : 'container_sea';

        $container = DB::table($table)->where('id', $id)->first();
        if (!$container) abort(404);

        $journal  = new journalController();
        $activity = $journal->activityForCostObject($costObjectKey, $id);

        // Pull every account that appears in the activity slice so we have
        // its type + normal_balance + display name. Anything that never
        // received a posting against this container is silently absent —
        // the report only shows what's actually attributable.
        $codes = array_keys($activity);
        $accounts = empty($codes)
            ? collect()
            : DB::table('chart_of_accounts')->whereIn('code', $codes)->get()->keyBy('code');

        $currencies = ['usd', 'eur', 'den', 'cny'];
        $revenue  = [];
        $expense  = [];
        $other    = []; // assets/liabilities — touched while a container is "open"
        $totals   = [
            'revenue' => array_fill_keys($currencies, 0.0),
            'expense' => array_fill_keys($currencies, 0.0),
            'net'     => array_fill_keys($currencies, 0.0),
        ];

        // Track cash flow against this container too — supplier and broker
        // payments currently land in prepayment asset accounts (1200/1300),
        // not expense accounts, so the bare expense total can be $0 even
        // when real cash left the till. This metric tells the operator
        // "how much cash did running this container consume?" — much closer
        // to the real-world question than the strict accrual net.
        $cashOutflow = array_fill_keys($currencies, 0.0);

        foreach ($activity as $code => $amounts) {
            $a = $accounts->get($code);
            if (!$a) continue;  // shouldn't happen, but stay defensive
            $sign = $a->normal_balance === 'debit' ? 1 : -1;
            $natural = array_fill_keys($currencies, 0.0);
            foreach ($currencies as $c) {
                $natural[$c] = $sign * (float) ($amounts[$c] ?? 0);
            }
            $row = ['code' => $a->code, 'name' => $a->name, 'amounts' => $natural];

            if ($a->type === 'revenue') {
                $revenue[] = $row;
                foreach ($currencies as $c) $totals['revenue'][$c] += $natural[$c];
            } elseif ($a->type === 'expense') {
                $expense[] = $row;
                foreach ($currencies as $c) $totals['expense'][$c] += $natural[$c];
            } else {
                // Asset / liability / equity activity for this container — usually
                // intermediate (AR clients on a charge, AP supplier on a payment).
                // Shown but excluded from net P&L.
                $other[] = $row + ['acct_type' => $a->type];
            }

            // Cash outflow: a credit (-1 * natural for debit-normal) on the
            // cash account 1000. Positive value = cash went out for this
            // container; negative = cash came in (rare — only direct cash
            // collected at the counter, e.g. the pay2 flow).
            if ($a->code === '1000') {
                foreach ($currencies as $c) {
                    $cashOutflow[$c] += -1 * $natural[$c];
                }
            }
        }
        foreach ($currencies as $c) {
            $totals['net'][$c] = $totals['revenue'][$c] - $totals['expense'][$c];
        }

        $lang = new langController();
        $data = new dataController();
        return view('pages.profits.container_ledger', [
            'container'      => $container,
            'kind'           => $type,
            'costObjectKey'  => $costObjectKey,
            'revenue'        => $revenue,
            'expense'        => $expense,
            'other'          => $other,
            'totals'         => $totals,
            'cashOutflow'    => $cashOutflow,
            'currencies'     => $currencies,
            'lang'           => $lang,
            'data'           => $data,
            'section'        => 'profits',
            'page'           => 'profits',
        ]);
    }

}
