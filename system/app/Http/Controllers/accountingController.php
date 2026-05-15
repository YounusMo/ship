<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;

class accountingController extends Controller
{
    private array $currencies = ['usd', 'eur', 'den', 'cny'];

    /* ============================================================
     *  Chart of Accounts
     * ============================================================ */
    public function chartIndex()
    {
        $this->requireAdmin();
        $accounts = DB::table('chart_of_accounts')
            ->orderBy('code')
            ->get();
        $lang = new langController();
        return view('pages.accounting.chart_of_accounts', [
            'accounts' => $accounts,
            'lang'     => $lang,
            'section'  => 'accounting',
            'page'     => 'chart',
        ]);
    }

    /* ============================================================
     *  Trial Balance
     *  GET /accounting/trial-balance?as_of=YYYY-MM-DD&period_from=YYYY-MM-DD&period_to=YYYY-MM-DD
     *  Balance-sheet rows = balances on entity tables as of now.
     *  P&L rows           = activity within the period (defaults to current month).
     *  Owner's Equity     = balancing figure (Assets - Liabilities - other Equity - Net Income).
     * ============================================================ */
    public function trialBalance(Request $request)
    {
        $this->requireAdmin();

        $asOf       = $request->get('as_of', date('Y-m-d'));
        $periodFrom = $request->get('period_from', date('Y-m-01'));
        $periodTo   = $request->get('period_to', date('Y-m-t'));

        $accounts = DB::table('chart_of_accounts')->where('is_active', true)->orderBy('code')->get();
        $balances = $this->deriveAccountBalances($periodFrom, $periodTo);

        $rows = [];
        $totals = ['debit' => array_fill_keys($this->currencies, 0.0), 'credit' => array_fill_keys($this->currencies, 0.0)];
        $netIncome = array_fill_keys($this->currencies, 0.0);

        foreach ($accounts as $a) {
            $amounts = $balances[$a->derivation_key] ?? array_fill_keys($this->currencies, 0.0);
            $row = [
                'code'           => $a->code,
                'name'           => $a->name,
                'type'           => $a->type,
                'normal_balance' => $a->normal_balance,
                'amounts'        => $amounts,
            ];
            $rows[] = $row;

            // Aggregate totals
            $bucket = $a->normal_balance;
            foreach ($this->currencies as $c) {
                $val = (float) ($amounts[$c] ?? 0.0);
                $totals[$bucket][$c] += $val;
                if ($a->type === 'revenue') $netIncome[$c] += $val;
                if ($a->type === 'expense') $netIncome[$c] -= $val;
            }
        }

        // Owner's equity plug: Assets - (Liabilities + non-plug Equity) - Net Income = plug
        // We display the computed plug for the 'owners_equity' row by overwriting its value.
        $assets       = array_fill_keys($this->currencies, 0.0);
        $liabilities  = array_fill_keys($this->currencies, 0.0);
        $otherEquity  = array_fill_keys($this->currencies, 0.0);
        foreach ($rows as $r) {
            foreach ($this->currencies as $c) {
                $v = (float) ($r['amounts'][$c] ?? 0.0);
                if ($r['type'] === 'asset')     $assets[$c] += $v;
                if ($r['type'] === 'liability') $liabilities[$c] += $v;
                if ($r['type'] === 'equity' && $r['code'] !== '3000') {
                    if ($r['normal_balance'] === 'credit') $otherEquity[$c] += $v;
                    else $otherEquity[$c] -= $v;
                }
            }
        }
        foreach ($rows as &$r) {
            if ($r['code'] === '3000') {
                foreach ($this->currencies as $c) {
                    $r['amounts'][$c] = $assets[$c] - $liabilities[$c] - $otherEquity[$c] - $netIncome[$c];
                }
            }
        }
        unset($r);

        // Recompute totals after plug
        $totals = ['debit' => array_fill_keys($this->currencies, 0.0), 'credit' => array_fill_keys($this->currencies, 0.0)];
        foreach ($rows as $r) {
            $bucket = $r['normal_balance'];
            foreach ($this->currencies as $c) {
                $totals[$bucket][$c] += (float) ($r['amounts'][$c] ?? 0.0);
            }
        }

        $dataController = new dataController();
        $lang = new langController();
        return view('pages.accounting.trial_balance', [
            'rows'        => $rows,
            'totals'      => $totals,
            'netIncome'   => $netIncome,
            'currencies'  => $this->currencies,
            'asOf'        => $asOf,
            'periodFrom'  => $periodFrom,
            'periodTo'    => $periodTo,
            'data'        => $dataController,
            'lang'        => $lang,
            'section'     => 'accounting',
            'page'        => 'trial_balance',
        ]);
    }

    /**
     * Derive per-currency amounts for each derivation_key used in the chart of accounts.
     * Balance-sheet keys come from entity tables; P&L keys come from transaction sums in [from,to].
     */
    private function deriveAccountBalances(string $from, string $to): array
    {
        $out = [];

        // ---- Balance sheet: cash ----
        $branches = DB::table('branches')->where('deleted', 0)->get();
        $cash = array_fill_keys($this->currencies, 0.0);
        foreach ($branches as $b) {
            foreach ($this->currencies as $c) {
                $cash[$c] += (float) ($b->{'balance_' . $c} ?? 0);
            }
        }
        $out['cash_total'] = $cash;

        // ---- Balance sheet: clients ----
        $clientDeposits = array_fill_keys($this->currencies, 0.0);
        $arClients      = array_fill_keys($this->currencies, 0.0);
        foreach ($this->currencies as $c) {
            $col = 'balance_' . $c;
            $clientDeposits[$c] = (float) DB::table('clients')
                ->where('deleted', 0)->where($col, '>', 0)->sum($col);
            $arClients[$c] = -1 * (float) DB::table('clients')
                ->where('deleted', 0)->where($col, '<', 0)->sum($col);
        }
        $out['client_deposits'] = $clientDeposits;
        $out['ar_clients']      = $arClients;

        // ---- Balance sheet: suppliers ----
        $apSuppliers = array_fill_keys($this->currencies, 0.0);
        $arSuppliers = array_fill_keys($this->currencies, 0.0);
        foreach ($this->currencies as $c) {
            $col = 'balance_' . $c;
            $apSuppliers[$c] = (float) DB::table('suppliers')
                ->where('deleted', 0)->where($col, '>', 0)->sum($col);
            $arSuppliers[$c] = -1 * (float) DB::table('suppliers')
                ->where('deleted', 0)->where($col, '<', 0)->sum($col);
        }
        $out['ap_suppliers'] = $apSuppliers;
        $out['ar_suppliers'] = $arSuppliers;

        // ---- Balance sheet: customs brokers ----
        $apBrokers = array_fill_keys($this->currencies, 0.0);
        $arBrokers = array_fill_keys($this->currencies, 0.0);
        foreach ($this->currencies as $c) {
            $col = 'balance_' . $c;
            $apBrokers[$c] = (float) DB::table('customs_brokers')
                ->where('deleted', 0)->where($col, '>', 0)->sum($col);
            $arBrokers[$c] = -1 * (float) DB::table('customs_brokers')
                ->where('deleted', 0)->where($col, '<', 0)->sum($col);
        }
        $out['ap_brokers'] = $apBrokers;
        $out['ar_brokers'] = $arBrokers;

        // ---- P&L: commission revenue (clients withdraw_commission within period) ----
        $commissionRev = array_fill_keys($this->currencies, 0.0);
        $rows = DB::table('clients_transactions')
            ->where('type', 'withdraw_commission')
            ->whereBetween('created_date', [$from, $to])
            ->select('currency', DB::raw('SUM(value) as total'))
            ->groupBy('currency')->get();
        foreach ($rows as $r) {
            if (in_array($r->currency, $this->currencies, true)) {
                $commissionRev[$r->currency] = (float) $r->total;
            }
        }
        $out['commission_revenue'] = $commissionRev;
        $out['shipping_revenue']   = array_fill_keys($this->currencies, 0.0);

        // ---- P&L: operating expenses (branches expense outflows excluding owner_*) ----
        $opEx = array_fill_keys($this->currencies, 0.0);
        $rows = DB::table('branches_transactions')
            ->where('plus_minus', '-')
            ->whereIn('type', ['expenses_branch', 'exp_withdraw'])
            ->whereBetween('created_date', [$from, $to])
            ->where(function ($q) {
                $q->whereNull('purpose')
                  ->orWhereNotIn('purpose', ['owner_drawing', 'owner_salary', 'owner_loan_out', 'owner_loan_repayment', 'owner_capital_in']);
            })
            ->select('currency', DB::raw('SUM(value) as total'))
            ->groupBy('currency')->get();
        foreach ($rows as $r) {
            if (in_array($r->currency, $this->currencies, true)) {
                $opEx[$r->currency] = (float) $r->total;
            }
        }
        $out['operating_expenses'] = $opEx;

        // ---- Owner drawings (equity, debit) ----
        $drawings = array_fill_keys($this->currencies, 0.0);
        $rows = DB::table('branches_transactions')
            ->where('plus_minus', '-')
            ->where('purpose', 'owner_drawing')
            ->whereBetween('created_date', [$from, $to])
            ->select('currency', DB::raw('SUM(value) as total'))
            ->groupBy('currency')->get();
        foreach ($rows as $r) {
            if (in_array($r->currency, $this->currencies, true)) {
                $drawings[$r->currency] = (float) $r->total;
            }
        }
        $out['owner_drawings'] = $drawings;

        // ---- Owner salary (expense) ----
        $salary = array_fill_keys($this->currencies, 0.0);
        $rows = DB::table('branches_transactions')
            ->where('plus_minus', '-')
            ->where('purpose', 'owner_salary')
            ->whereBetween('created_date', [$from, $to])
            ->select('currency', DB::raw('SUM(value) as total'))
            ->groupBy('currency')->get();
        foreach ($rows as $r) {
            if (in_array($r->currency, $this->currencies, true)) {
                $salary[$r->currency] = (float) $r->total;
            }
        }
        $out['owner_salary'] = $salary;

        // owners_equity is computed as plug in trialBalance(); leave zero here.
        $out['owners_equity'] = array_fill_keys($this->currencies, 0.0);

        return $out;
    }

    /* ============================================================
     *  AR Aging
     *  GET /accounting/ar-aging?currency=usd
     * ============================================================ */
    public function arAging(Request $request)
    {
        $this->requireAdmin();
        $today = date('Y-m-d');
        $clients = DB::table('clients')
            ->where('deleted', 0)
            ->where(function ($q) {
                $q->where('balance_usd', '!=', 0)
                  ->orWhere('balance_eur', '!=', 0)
                  ->orWhere('balance_den', '!=', 0)
                  ->orWhere('balance_cny', '!=', 0);
            })
            ->get(['id', 'code', 'name', 'balance_usd', 'balance_eur', 'balance_den', 'balance_cny']);

        // Determine "last activity" per client via clients_transactions
        $clientIds = $clients->pluck('id')->all();
        $lastActivity = [];
        if (!empty($clientIds)) {
            $rows = DB::table('clients_transactions')
                ->whereIn('client_id', $clientIds)
                ->select('client_id', DB::raw('MAX(created_date) as last_date'))
                ->groupBy('client_id')->get();
            foreach ($rows as $r) {
                $lastActivity[$r->client_id] = $r->last_date;
            }
        }

        $buckets = ['current' => '0-30 days', 'b31_60' => '31-60', 'b61_90' => '61-90', 'b91_180' => '91-180', 'b180_plus' => '180+'];
        $aging = [];
        $bucketTotals = [];
        foreach ($this->currencies as $c) {
            $bucketTotals[$c] = array_fill_keys(array_keys($buckets), 0.0);
        }

        foreach ($clients as $cl) {
            $lastDate = $lastActivity[$cl->id] ?? null;
            $days = $lastDate ? (int) round((strtotime($today) - strtotime($lastDate)) / 86400) : 9999;
            $bucket = $days <= 30 ? 'current'
                : ($days <= 60 ? 'b31_60'
                : ($days <= 90 ? 'b61_90'
                : ($days <= 180 ? 'b91_180' : 'b180_plus')));
            $row = [
                'id'         => $cl->id,
                'code'       => $cl->code,
                'name'       => $cl->name,
                'last_date'  => $lastDate,
                'days'       => $days,
                'bucket'     => $bucket,
                'balances'   => [],
            ];
            foreach ($this->currencies as $c) {
                $val = (float) $cl->{'balance_' . $c};
                $row['balances'][$c] = $val;
                $bucketTotals[$c][$bucket] += $val;
            }
            $aging[] = $row;
        }

        // Sort: oldest first
        usort($aging, fn($a, $b) => $b['days'] <=> $a['days']);

        $dataController = new dataController();
        $lang = new langController();
        return view('pages.accounting.ar_aging', [
            'aging'        => $aging,
            'buckets'      => $buckets,
            'bucketTotals' => $bucketTotals,
            'currencies'   => $this->currencies,
            'data'         => $dataController,
            'lang'         => $lang,
            'section'      => 'accounting',
            'page'         => 'ar_aging',
        ]);
    }

    /* ============================================================
     *  Periods (close/reopen)
     * ============================================================ */
    public function periodsIndex()
    {
        $this->requireAdmin();
        // Ensure periods exist for the last 24 months and the current month.
        $this->backfillPeriods(24);
        $periods = DB::table('accounting_periods')
            ->orderByDesc('period_year')->orderByDesc('period_month')->limit(60)->get();
        $lang = new langController();
        return view('pages.accounting.periods', [
            'periods' => $periods,
            'lang'    => $lang,
            'section' => 'accounting',
            'page'    => 'periods',
        ]);
    }

    public function periodClose(Request $request, $id)
    {
        $this->requireAdmin();
        $p = DB::table('accounting_periods')->where('id', $id)->first();
        if (!$p) abort(404);
        if ($p->status === 'closed') {
            return response()->json(['type' => 'already_closed'], 200);
        }
        $user = auth()->user();
        DB::table('accounting_periods')->where('id', $id)->update([
            'status'              => 'closed',
            'closed_by_user_id'   => $user->id,
            'closed_by_user_name' => $user->name,
            'closed_at'           => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
        $this->logAudit('period_close', 'accounting_periods', $id,
            ['period_year' => $p->period_year, 'period_month' => $p->period_month],
            'Closed accounting period ' . $p->period_year . '-' . str_pad($p->period_month, 2, '0', STR_PAD_LEFT));
        return response()->json(['type' => 'success']);
    }

    public function periodReopen(Request $request, $id)
    {
        $this->requireAdmin();
        $p = DB::table('accounting_periods')->where('id', $id)->first();
        if (!$p) abort(404);
        if ($p->status === 'open') {
            return response()->json(['type' => 'already_open'], 200);
        }
        $user = auth()->user();
        DB::table('accounting_periods')->where('id', $id)->update([
            'status'                => 'open',
            'reopened_by_user_id'   => $user->id,
            'reopened_by_user_name' => $user->name,
            'reopened_at'           => date('Y-m-d H:i:s'),
            'notes'                 => mb_substr((string) $request->reason, 0, 500),
            'updated_at'            => date('Y-m-d H:i:s'),
        ]);
        $this->logAudit('period_reopen', 'accounting_periods', $id,
            ['period_year' => $p->period_year, 'period_month' => $p->period_month, 'reason' => $request->reason],
            'Reopened accounting period ' . $p->period_year . '-' . str_pad($p->period_month, 2, '0', STR_PAD_LEFT));
        return response()->json(['type' => 'success']);
    }

    private function backfillPeriods(int $monthsBack = 24): void
    {
        $now = strtotime(date('Y-m-01'));
        $rows = [];
        for ($i = $monthsBack; $i >= 0; $i--) {
            $ts = strtotime("-$i months", $now);
            $y = (int) date('Y', $ts);
            $m = (int) date('m', $ts);
            $exists = DB::table('accounting_periods')
                ->where('period_year', $y)->where('period_month', $m)->exists();
            if (!$exists) {
                $rows[] = [
                    'period_year'  => $y,
                    'period_month' => $m,
                    'period_start' => date('Y-m-01', $ts),
                    'period_end'   => date('Y-m-t', $ts),
                    'status'       => 'open',
                    'created_at'   => date('Y-m-d H:i:s'),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ];
            }
        }
        if (!empty($rows)) {
            DB::table('accounting_periods')->insert($rows);
        }
    }

    /* ============================================================
     *  Cash Counts (reconciliation)
     * ============================================================ */
    public function cashCountIndex()
    {
        $branches = DB::table('branches')->where('deleted', 0)->get();
        $recent = DB::table('cash_counts')->orderByDesc('id')->limit(50)->get();
        $lang = new langController();
        $dataController = new dataController();
        return view('pages.accounting.cash_counts', [
            'branches' => $branches,
            'recent'   => $recent,
            'lang'     => $lang,
            'data'     => $dataController,
            'section'  => 'accounting',
            'page'     => 'cash_counts',
        ]);
    }

    public function cashCountStore(Request $request)
    {
        $branchId = (int) $request->branch_id;
        $currency = (string) $request->currency;
        $counted  = floatval($request->counted_amount);
        $notes    = mb_substr((string) $request->notes, 0, 500);

        if (!in_array($currency, $this->currencies, true)) {
            return response()->json(['type' => 'invalid_currency'], 422);
        }
        $branch = DB::table('branches')->where('id', $branchId)->where('deleted', 0)->first();
        if (!$branch) {
            return response()->json(['type' => 'branch_not_found'], 404);
        }

        $systemBalance = (float) ($branch->{'balance_' . $currency} ?? 0);
        $variance      = $counted - $systemBalance;
        $user          = auth()->user();

        $id = DB::table('cash_counts')->insertGetId([
            'branch_id'            => $branchId,
            'currency'             => $currency,
            'system_balance'       => $systemBalance,
            'counted_amount'       => $counted,
            'variance'             => $variance,
            'count_date'           => date('Y-m-d'),
            'counted_by_user_id'   => $user?->id,
            'counted_by_user_name' => $user?->name,
            'counted_at'           => date('Y-m-d H:i:s'),
            'notes'                => $notes,
        ]);

        $this->logAudit('cash_count', 'cash_counts', $id, [
            'branch_id'      => $branchId,
            'currency'       => $currency,
            'system_balance' => $systemBalance,
            'counted_amount' => $counted,
            'variance'       => $variance,
        ], 'Cash count');

        return response()->json(['type' => 'success', 'id' => $id, 'variance' => $variance]);
    }

    public function cashCountAdjust(Request $request, $id)
    {
        $this->requireAdmin();
        $cc = DB::table('cash_counts')->where('id', $id)->first();
        if (!$cc) abort(404);
        if ($cc->adjustment_posted) {
            return response()->json(['type' => 'already_posted'], 200);
        }
        if (abs((float) $cc->variance) < 0.0001) {
            return response()->json(['type' => 'no_variance'], 200);
        }

        // Insert treasury transaction reflecting the variance.
        $user = auth()->user();
        $plusMinus = $cc->variance > 0 ? '+' : '-';
        $purpose   = $cc->variance > 0 ? 'cash_count_over' : 'cash_count_short';
        $value     = abs((float) $cc->variance);

        $dataController = new dataController();
        $txnNumber = $dataController->transaction_number('cash_count_adj', $cc->id);
        $autoId = ((int) DB::table('branches_transactions')->where('branch', $cc->branch_id)->max('auto_id')) + 1;

        $branchTxnId = DB::table('branches_transactions')->insertGetId([
            'type'         => $cc->variance > 0 ? 'deposit' : 'withdraw',
            'data'         => json_encode(['cash_count_id' => $cc->id]),
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'created_by'   => $user->id,
            'value'        => $value,
            'currency'     => $cc->currency,
            'plus_minus'   => $plusMinus,
            'branch'       => $cc->branch_id,
            'notes'        => 'Cash count adjustment',
            'purpose'      => $purpose,
            'auto_id'      => $autoId,
            'transaction_number' => $txnNumber,
        ]);

        // Update branch balance
        $col = 'balance_' . $cc->currency;
        if ($plusMinus === '+') {
            DB::table('branches')->where('id', $cc->branch_id)->increment($col, $value);
        } else {
            DB::table('branches')->where('id', $cc->branch_id)->decrement($col, $value);
        }

        DB::table('cash_counts')->where('id', $id)->update([
            'adjustment_posted'        => true,
            'adjustment_transaction_id'=> $branchTxnId,
        ]);

        $this->logAudit('cash_count_adjust', 'cash_counts', $id, [
            'variance' => $cc->variance,
            'currency' => $cc->currency,
            'branch_id'=> $cc->branch_id,
            'branch_transaction_id' => $branchTxnId,
        ], 'Posted cash-count variance adjustment');

        return response()->json(['type' => 'success', 'branch_transaction_id' => $branchTxnId]);
    }

    /* ============================================================
     *  FX Rate History
     * ============================================================ */
    public function fxHistory()
    {
        $this->requireAdmin();
        $rows = DB::table('fx_rate_history')->orderByDesc('id')->limit(500)->get();
        $lang = new langController();
        return view('pages.accounting.fx_history', [
            'rows'    => $rows,
            'lang'    => $lang,
            'section' => 'accounting',
            'page'    => 'fx_history',
        ]);
    }

    /* ============================================================
     *  Prepayments
     * ============================================================ */
    public function prepaymentsIndex(Request $request)
    {
        $status = $request->get('status', 'open');
        $q = DB::table('prepayments')
            ->leftJoin('clients', 'clients.id', '=', 'prepayments.client_id')
            ->select('prepayments.*', 'clients.name as client_name', 'clients.code as client_code')
            ->orderByDesc('prepayments.id');
        if ($status !== 'all') {
            $q->where('prepayments.status', $status);
        }
        $rows = $q->limit(500)->get();

        // Surface client deposits with purpose=prepayment_received that don't yet
        // have a prepayment row, so staff can register them.
        $danglingRaw = DB::table('clients_transactions')
            ->leftJoin('prepayments', 'prepayments.source_transaction_id', '=', 'clients_transactions.id')
            ->leftJoin('clients', 'clients.id', '=', 'clients_transactions.client_id')
            ->whereNull('prepayments.id')
            ->where('clients_transactions.purpose', 'prepayment_received')
            ->where('clients_transactions.type', 'deposit')
            ->where('clients_transactions.plus_minus', '+')
            ->select('clients_transactions.*', 'clients.name as client_name', 'clients.code as client_code')
            ->orderByDesc('clients_transactions.id')
            ->limit(200)
            ->get();

        $lang = new langController();
        $dataController = new dataController();
        return view('pages.accounting.prepayments', [
            'rows'      => $rows,
            'dangling'  => $danglingRaw,
            'status'    => $status,
            'lang'      => $lang,
            'data'      => $dataController,
            'section'   => 'accounting',
            'page'      => 'prepayments',
        ]);
    }

    public function prepaymentRegister(Request $request)
    {
        $txnId = (int) $request->source_transaction_id;
        $txn = DB::table('clients_transactions')->where('id', $txnId)->first();
        if (!$txn || $txn->type !== 'deposit' || $txn->plus_minus !== '+') {
            return response()->json(['type' => 'invalid_source'], 422);
        }
        if (DB::table('prepayments')->where('source_transaction_id', $txnId)->exists()) {
            return response()->json(['type' => 'already_registered'], 200);
        }
        $id = DB::table('prepayments')->insertGetId([
            'client_id'             => $txn->client_id,
            'source_transaction_id' => $txn->id,
            'currency'              => $txn->currency,
            'original_amount'       => (float) $txn->value,
            'applied_amount'        => 0,
            'remaining_amount'      => (float) $txn->value,
            'status'                => 'open',
            'received_date'         => $txn->created_date,
            'created_by_user_id'    => auth()->user()->id,
            'created_at'            => date('Y-m-d H:i:s'),
            'updated_at'            => date('Y-m-d H:i:s'),
        ]);
        $this->logAudit('prepayment_register', 'prepayments', $id, [
            'client_id'             => $txn->client_id,
            'source_transaction_id' => $txn->id,
            'amount'                => (float) $txn->value,
            'currency'              => $txn->currency,
        ], 'Registered prepayment');
        return response()->json(['type' => 'success', 'id' => $id]);
    }

    public function prepaymentApply(Request $request, $id)
    {
        $p = DB::table('prepayments')->where('id', $id)->first();
        if (!$p) abort(404);
        if ($p->status !== 'open') {
            return response()->json(['type' => 'not_open'], 422);
        }
        $amount = floatval($request->amount);
        if ($amount <= 0) {
            return response()->json(['type' => 'invalid_amount'], 422);
        }
        if ($amount > (float) $p->remaining_amount + 0.0001) {
            return response()->json(['type' => 'over_apply'], 422);
        }
        $user = auth()->user();
        DB::transaction(function () use ($p, $amount, $request, $user, $id) {
            DB::table('prepayment_applications')->insert([
                'prepayment_id'    => $id,
                'amount'           => $amount,
                'applied_to_ref'   => mb_substr((string) $request->applied_to_ref, 0, 191),
                'applied_to_type'  => mb_substr((string) $request->applied_to_type, 0, 64),
                'applied_to_id'    => is_numeric($request->applied_to_id) ? (int) $request->applied_to_id : null,
                'notes'            => mb_substr((string) $request->notes, 0, 500),
                'applied_by_user_id'   => $user->id,
                'applied_by_user_name' => $user->name,
                'applied_at'       => date('Y-m-d H:i:s'),
            ]);
            $newApplied   = (float) $p->applied_amount + $amount;
            $newRemaining = (float) $p->original_amount - $newApplied;
            $newStatus    = $newRemaining <= 0.0001 ? 'fully_applied' : 'open';
            DB::table('prepayments')->where('id', $id)->update([
                'applied_amount'   => $newApplied,
                'remaining_amount' => $newRemaining,
                'status'           => $newStatus,
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);
        });
        $this->logAudit('prepayment_apply', 'prepayments', $id, [
            'amount'        => $amount,
            'applied_to'    => $request->applied_to_ref,
        ], 'Applied prepayment portion');
        return response()->json(['type' => 'success']);
    }

    /* ============================================================
     *  Owners
     * ============================================================ */
    public function ownersIndex()
    {
        $owners = DB::table('owners')->where('deleted', 0)->orderBy('name')->get();
        $lang = new langController();
        return view('pages.accounting.owners', [
            'owners'  => $owners,
            'lang'    => $lang,
            'section' => 'accounting',
            'page'    => 'owners',
        ]);
    }

    public function ownersStore(Request $request)
    {
        $this->requireAdmin();
        $id = DB::table('owners')->insertGetId([
            'name'             => mb_substr((string) $request->name, 0, 191),
            'name_en'          => mb_substr((string) $request->name_en, 0, 191),
            'share_percentage' => floatval($request->share_percentage),
            'national_id'      => mb_substr((string) $request->national_id, 0, 64),
            'phone'            => mb_substr((string) $request->phone, 0, 64),
            'email'            => mb_substr((string) $request->email, 0, 191),
            'active'           => $request->boolean('active', true),
            'notes'            => mb_substr((string) $request->notes, 0, 500),
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        $this->logAudit('owner_create', 'owners', $id, ['name' => $request->name], 'Created owner');
        return response()->json(['type' => 'success', 'id' => $id]);
    }

    public function ownersUpdate(Request $request, $id)
    {
        $this->requireAdmin();
        DB::table('owners')->where('id', $id)->update([
            'name'             => mb_substr((string) $request->name, 0, 191),
            'name_en'          => mb_substr((string) $request->name_en, 0, 191),
            'share_percentage' => floatval($request->share_percentage),
            'national_id'      => mb_substr((string) $request->national_id, 0, 64),
            'phone'            => mb_substr((string) $request->phone, 0, 64),
            'email'            => mb_substr((string) $request->email, 0, 191),
            'active'           => $request->boolean('active', true),
            'notes'            => mb_substr((string) $request->notes, 0, 500),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        $this->logAudit('owner_update', 'owners', $id, [], 'Updated owner');
        return response()->json(['type' => 'success']);
    }

    public function ownersDelete($id)
    {
        $this->requireAdmin();
        DB::table('owners')->where('id', $id)->update([
            'deleted'    => true,
            'active'     => false,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->logAudit('owner_delete', 'owners', $id, [], 'Soft-deleted owner');
        return response()->json(['type' => 'success']);
    }

    public function ownersLedger(Request $request)
    {
        $this->requireAdmin();
        $from = $request->get('from', date('Y-01-01'));
        $to   = $request->get('to', date('Y-12-31'));
        $ownerId = $request->get('owner_id');

        $q = DB::table('branches_transactions')
            ->whereBetween('created_date', [$from, $to])
            ->whereIn('purpose', ['owner_drawing', 'owner_salary', 'owner_loan_out', 'owner_loan_repayment', 'owner_capital_in'])
            ->orderBy('created_date')->orderBy('created_time');
        if (!empty($ownerId)) $q->where('owner_id', $ownerId);
        $rows = $q->get();

        $owners = DB::table('owners')->where('deleted', 0)->orderBy('name')->get();
        $lang = new langController();
        $dataController = new dataController();
        return view('pages.accounting.owners_ledger', [
            'rows'    => $rows,
            'owners'  => $owners,
            'from'    => $from,
            'to'      => $to,
            'ownerId' => $ownerId,
            'lang'    => $lang,
            'data'    => $dataController,
            'section' => 'accounting',
            'page'    => 'owners_ledger',
        ]);
    }

    /* ============================================================
     *  Helpers
     * ============================================================ */
    private function requireAdmin(): void
    {
        $u = auth()->user();
        if (!$u || $u->type !== 'admin') {
            abort(403);
        }
    }
}
