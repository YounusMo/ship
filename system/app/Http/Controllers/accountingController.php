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
     *  Trial Balance — DEPRECATED entry point
     *
     *  The journal is now the single source of truth, so this URL
     *  redirects to /accounting/journal-trial-balance. Old links and
     *  bookmarks keep working without revealing the entity-derived view
     *  (which by construction can drift from the journal — that's what
     *  the drift detector is for).
     *
     *  The deriveAccountBalances() helper below is kept private and is
     *  only consumed by driftReport(); once drift goes permanently green
     *  it can be retired too.
     * ============================================================ */
    public function trialBalance(Request $request)
    {
        $this->requireAdmin();
        $qs = http_build_query(array_filter([
            'as_of' => $request->get('as_of') ?: $request->get('period_to'),
        ]));
        return redirect('/accounting/journal-trial-balance' . ($qs ? '?' . $qs : ''));
    }

    /**
     * Derive per-currency amounts for each derivation_key used in the chart
     * of accounts. Balance-sheet keys come from entity tables; P&L keys
     * come from transaction sums in [from,to].
     *
     * Only used by driftReport() — every report-facing path now reads the
     * journal directly via journalController::balancesAsOf() /
     * activityBetween().
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
        // Convention (verified against suppliersController::deposit): a supplier
        // deposit is money flowing OUT of our treasury INTO the supplier's
        // balance. So positive supplier balance = we prepaid them = ASSET
        // (prepaid); negative balance = we owe them more than we've paid = AP.
        $apSuppliers = array_fill_keys($this->currencies, 0.0);
        $arSuppliers = array_fill_keys($this->currencies, 0.0);
        foreach ($this->currencies as $c) {
            $col = 'balance_' . $c;
            $arSuppliers[$c] = (float) DB::table('suppliers')
                ->where('deleted', 0)->where($col, '>', 0)->sum($col);
            $apSuppliers[$c] = -1 * (float) DB::table('suppliers')
                ->where('deleted', 0)->where($col, '<', 0)->sum($col);
        }
        $out['ap_suppliers'] = $apSuppliers;
        $out['ar_suppliers'] = $arSuppliers;

        // ---- Balance sheet: customs brokers ----
        // Same convention as suppliers.
        $apBrokers = array_fill_keys($this->currencies, 0.0);
        $arBrokers = array_fill_keys($this->currencies, 0.0);
        foreach ($this->currencies as $c) {
            $col = 'balance_' . $c;
            $arBrokers[$c] = (float) DB::table('customs_brokers')
                ->where('deleted', 0)->where($col, '>', 0)->sum($col);
            $apBrokers[$c] = -1 * (float) DB::table('customs_brokers')
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
        // plus_minus is stored as the word 'minus' / 'plus' across every
        // transaction table — using the sign characters would silently match
        // nothing and inflate owner's equity by absorbing the missing expense.
        $opEx = array_fill_keys($this->currencies, 0.0);
        $rows = DB::table('branches_transactions')
            ->where('plus_minus', 'minus')
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
            ->where('plus_minus', 'minus')
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
            ->where('plus_minus', 'minus')
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
     *  Daily transactions journal (HTML)
     *  GET /accounting/journal?date=YYYY-MM-DD
     *  Merges clients_transactions + branches_transactions +
     *  suppliers_transactions + customs_brokers_transactions for the
     *  date, ordered chronologically. End-of-day review tool.
     * ============================================================ */
    public function dailyJournal(Request $request)
    {
        $this->requireAdmin();
        $date = $request->get('date', date('Y-m-d'));

        $rows = [];

        $clients = DB::table('clients_transactions')
            ->leftJoin('clients', 'clients.id', '=', 'clients_transactions.client_id')
            ->where('clients_transactions.created_date', $date)
            ->select('clients_transactions.*', 'clients.code as party_code', 'clients.name as party_name')
            ->get();
        foreach ($clients as $r) {
            $rows[] = [
                'time'     => $r->created_time,
                'source'   => 'client',
                'party'    => trim(($r->party_code ?? '') . ' — ' . ($r->party_name ?? '')),
                'type'     => $r->type,
                'value'    => (float) $r->value,
                'currency' => $r->currency,
                'sign'     => $r->plus_minus,
                'branch'   => $r->branch,
                'notes'    => $r->notes,
                'purpose'  => $r->purpose,
                'user_id'  => $r->created_by,
                'auto_id'  => $r->auto_id,
            ];
        }

        $branches = DB::table('branches_transactions')
            ->where('created_date', $date)->get();
        foreach ($branches as $r) {
            $rows[] = [
                'time'     => $r->created_time,
                'source'   => 'branch',
                'party'    => 'Treasury #' . $r->branch,
                'type'     => $r->type,
                'value'    => (float) $r->value,
                'currency' => $r->currency,
                'sign'     => $r->plus_minus,
                'branch'   => $r->branch,
                'notes'    => $r->notes,
                'purpose'  => $r->purpose,
                'user_id'  => $r->created_by,
                'auto_id'  => $r->auto_id,
            ];
        }

        $suppliers = DB::table('suppliers_transactions')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'suppliers_transactions.supplier_id')
            ->where('suppliers_transactions.created_date', $date)
            ->select('suppliers_transactions.*', 'suppliers.name as party_name')
            ->get();
        foreach ($suppliers as $r) {
            $rows[] = [
                'time'     => $r->created_time,
                'source'   => 'supplier',
                'party'    => $r->party_name ?? ('#' . $r->supplier_id),
                'type'     => $r->type,
                'value'    => (float) $r->value,
                'currency' => $r->currency,
                'sign'     => $r->plus_minus,
                'branch'   => $r->branch,
                'notes'    => $r->notes,
                'purpose'  => $r->purpose,
                'user_id'  => $r->created_by,
                'auto_id'  => $r->auto_id,
            ];
        }

        $brokers = DB::table('customs_brokers_transactions')
            ->leftJoin('customs_brokers', 'customs_brokers.id', '=', 'customs_brokers_transactions.broker_id')
            ->where('customs_brokers_transactions.created_date', $date)
            ->select('customs_brokers_transactions.*', 'customs_brokers.name as party_name')
            ->get();
        foreach ($brokers as $r) {
            $rows[] = [
                'time'     => $r->created_time,
                'source'   => 'broker',
                'party'    => $r->party_name ?? ('#' . $r->broker_id),
                'type'     => $r->type,
                'value'    => (float) $r->value,
                'currency' => $r->currency,
                'sign'     => $r->plus_minus,
                'branch'   => $r->branch,
                'notes'    => $r->notes,
                'purpose'  => $r->purpose,
                'user_id'  => $r->created_by,
                'auto_id'  => $r->auto_id,
            ];
        }

        usort($rows, fn($a, $b) => strcmp($a['time'] ?? '', $b['time'] ?? ''));

        // Resolve user names
        $userIds = array_unique(array_filter(array_column($rows, 'user_id')));
        $users = $userIds
            ? DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id')->all()
            : [];

        // Branch lookup
        $branchIds = array_unique(array_filter(array_column($rows, 'branch')));
        $branchNames = $branchIds
            ? DB::table('branches')->whereIn('id', $branchIds)->pluck('name', 'id')->all()
            : [];

        // Per-currency cash net change from treasury_transactions (the
        // canonical cash ledger; branches_transactions is only written for
        // explicit branch-level actions like expenses or transfers).
        $totals = ['usd' => 0.0, 'eur' => 0.0, 'den' => 0.0, 'cny' => 0.0];
        $tr = DB::table('treasury_transactions')
            ->where('created_date', $date)
            ->select('currency', 'plus_minus', DB::raw('SUM(value) as total'))
            ->groupBy('currency', 'plus_minus')->get();
        foreach ($tr as $r) {
            if (!isset($totals[$r->currency])) continue;
            $sign = ($r->plus_minus === 'plus' || $r->plus_minus === '+') ? 1 : -1;
            $totals[$r->currency] += $sign * (float) $r->total;
        }

        $lang = new langController();
        $data = new dataController();
        return view('pages.accounting.daily_journal', [
            'rows'        => $rows,
            'users'       => $users,
            'branchNames' => $branchNames,
            'totals'      => $totals,
            'date'        => $date,
            'lang'        => $lang,
            'data'        => $data,
            'section'     => 'accounting',
            'page'        => 'journal',
        ]);
    }

    /* ============================================================
     *  Cash flow statement (PDF) — direct method.
     *  GET /accounting/cash-flow?from=&to=
     *
     *  Direct method, sourced entirely from journal_lines. We walk every
     *  open journal_entry whose entry_date falls in [from, to] and that
     *  touches the cash account (1000). The cash line gives us the per-
     *  currency signed delta; the entry's `kind` tells us which bucket
     *  the movement belongs to.
     *
     *  Beginning / ending cash come from journalController::balancesAsOf()
     *  evaluated at the day before $from and at $to respectively, so the
     *  cash equation always reconciles to the journal — no reaching into
     *  branches.balance_* anymore.
     * ============================================================ */
    public function cashFlowStatement(Request $request)
    {
        $this->requireAdmin();
        $from = $request->get('from', date('Y-m-01'));
        $to   = $request->get('to',   date('Y-m-t'));

        $journal = new journalController();

        // Cash positions snap directly off the journal so the report ties
        // out: ending - beginning == sum(per-bucket signed activity).
        $balanceBefore = $journal->balancesAsOf(date('Y-m-d', strtotime($from . ' -1 day')));
        $balanceAtEnd  = $journal->balancesAsOf($to);

        $cashCode = '1000';
        $beginningCash = array_fill_keys($this->currencies, 0.0);
        $endingCash    = array_fill_keys($this->currencies, 0.0);
        foreach ($this->currencies as $c) {
            // Cash is debit-normal: signed_net (dr-cr) IS the natural figure.
            $beginningCash[$c] = (float) ($balanceBefore[$cashCode][$c] ?? 0);
            $endingCash[$c]    = (float) ($balanceAtEnd[$cashCode][$c] ?? 0);
        }

        // Buckets are keyed by journal `kind`. Anything we can't map gets
        // collected into other_inflow / other_outflow so a new kind never
        // silently disappears from the statement.
        $categories = [
            'inflow_client'      => ['label' => 'Cash received from clients',   'sign' => '+'],
            'outflow_client'     => ['label' => 'Cash paid to clients',         'sign' => '-'],
            'outflow_supplier'   => ['label' => 'Cash paid to suppliers',       'sign' => '-'],
            'inflow_supplier'    => ['label' => 'Refunds from suppliers',       'sign' => '+'],
            'outflow_broker'     => ['label' => 'Cash paid to customs brokers', 'sign' => '-'],
            'inflow_broker'      => ['label' => 'Refunds from customs brokers', 'sign' => '+'],
            'outflow_opex'       => ['label' => 'Operating expenses paid',      'sign' => '-'],
            'outflow_owner_draw' => ['label' => 'Owner drawings',               'sign' => '-'],
            'outflow_owner_sal'  => ['label' => 'Owner salary paid',            'sign' => '-'],
            'inflow_owner_cap'   => ['label' => 'Owner capital contributions',  'sign' => '+'],
            'other_inflow'       => ['label' => 'Other inflows',                'sign' => '+'],
            'other_outflow'      => ['label' => 'Other outflows',               'sign' => '-'],
        ];
        $sums = array_fill_keys(array_keys($categories), array_fill_keys($this->currencies, 0.0));

        // Pull every cash-touching line in the period along with its parent
        // entry's kind. No status filter — both halves of a reversal pair
        // (original status='reversed' + counter kind='reversal') need to be
        // included so the cash equation `ending - beginning == netChange`
        // reconciles for cross-period reversals. Same-period reversals
        // cancel naturally because their dr/cr are equal and opposite.
        $cashLines = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.entry_id')
            ->where('journal_lines.account_code', $cashCode)
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->select(
                'journal_entries.kind',
                'journal_lines.currency',
                'journal_lines.dr',
                'journal_lines.cr'
            )
            ->get();

        foreach ($cashLines as $l) {
            if (!in_array($l->currency, $this->currencies, true)) continue;
            $delta = (float) $l->dr - (float) $l->cr;   // +inflow / -outflow
            if (abs($delta) < 0.0001) continue;
            $bucket = $this->classifyJournalCashKind($l->kind, $delta > 0);
            // Sums are stored unsigned — the category's sign drives display.
            $sums[$bucket][$l->currency] += abs($delta);
        }

        $netChange = array_fill_keys($this->currencies, 0.0);
        foreach ($categories as $key => $cat) {
            $sign = $cat['sign'] === '+' ? 1 : -1;
            foreach ($this->currencies as $c) {
                $netChange[$c] += $sign * (float) ($sums[$key][$c] ?? 0);
            }
        }

        $settings = (new settingsController())->get();
        $lang = new langController();
        $data = new dataController();
        $html = view('pages.accounting.cash_flow_pdf', compact(
            'from', 'to', 'categories', 'sums',
            'beginningCash', 'endingCash', 'netChange',
            'settings', 'lang', 'data'
        ))->render();

        $isRtl = (auth()->user()->lang ?? 'en') === 'ar';
        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'default_font'   => 'dejavusans',
            'directionality' => $isRtl ? 'rtl' : 'ltr',
            'margin_top'     => 10, 'margin_bottom' => 10,
            'margin_left'    => 14, 'margin_right' => 14,
        ]);
        $mpdf->WriteHTML($html);
        $filename = 'cash-flow-' . $from . '-' . $to . '.pdf';
        return response($mpdf->Output($filename, 'I'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /**
     * Map a journal-entry kind (combined with the cash-line direction) onto
     * a cash-flow category key. Buckets that don't have a deterministic
     * direction by kind alone use $isInflow as the tiebreaker. Anything we
     * don't know about falls into other_inflow / other_outflow so a newly
     * introduced kind shows up in the report instead of vanishing.
     */
    private function classifyJournalCashKind(?string $kind, bool $isInflow): string
    {
        switch ($kind) {
            case 'client_deposit':              return 'inflow_client';
            case 'client_withdraw':             return 'outflow_client';

            case 'supplier_deposit':            return 'outflow_supplier';
            case 'supplier_refund':             return 'inflow_supplier';

            case 'broker_deposit':              return 'outflow_broker';
            case 'broker_refund':               return 'inflow_broker';

            case 'expense':                     return 'outflow_opex';
            case 'owner_drawing':               return 'outflow_owner_draw';
            case 'owner_salary':                return 'outflow_owner_sal';

            // branch_deposit covers cash injections — owner capital is the
            // dominant case, so route there. Genuinely odd inflows fall
            // through to other_inflow via the default below.
            case 'branch_deposit':              return 'inflow_owner_cap';

            // Cash count corrections move cash but aren't operating activity
            // — surface them in "other" so they're visible without skewing
            // the operating buckets.
            case 'cash_count_over':             return 'other_inflow';
            case 'cash_count_short':            return 'other_outflow';

            // Currency conversions touch cash on both sides per currency,
            // but no cash leaves the business in aggregate — show them in
            // "other" so the per-currency view is honest about movement.
            case 'client_currency_transfer':    return $isInflow ? 'other_inflow' : 'other_outflow';

            // Reversal counter entries: same-period reversals net to zero
            // against their original here and disappear. Cross-period
            // reversals surface in "other" so the cash equation still ties
            // out (ending - beginning == netChange) — the alternative is
            // to silently hide them and break the reconciliation.
            case 'reversal':                    return $isInflow ? 'other_inflow' : 'other_outflow';

            // Branch-to-branch transfers (transfer_in / transfer_out) and
            // commission entries don't touch cash, so they shouldn't reach
            // here — but if they do (future routing) put them in "other".
        }
        return $isInflow ? 'other_inflow' : 'other_outflow';
    }

    /* ============================================================
     *  Supplier aging
     *  GET /accounting/supplier-aging
     * ============================================================ */
    public function supplierAging(Request $request)
    {
        $this->requireAdmin();
        return $this->entityAging('suppliers_transactions', 'suppliers', 'supplier_id', 'supplier', $request);
    }

    public function brokerAging(Request $request)
    {
        $this->requireAdmin();
        return $this->entityAging('customs_brokers_transactions', 'customs_brokers', 'broker_id', 'broker', $request);
    }

    /**
     * Generic aging report builder for entity tables that follow the
     * (positive balance = we prepaid them, negative balance = we owe them)
     * convention. Used by supplierAging() and brokerAging().
     */
    private function entityAging(string $txTable, string $entityTable, string $fkColumn, string $entityKind, Request $request)
    {
        $lang = new langController();
        $today = date('Y-m-d');
        $entities = DB::table($entityTable)
            ->where('deleted', 0)
            ->where(function ($q) {
                $q->where('balance_usd', '!=', 0)
                  ->orWhere('balance_eur', '!=', 0)
                  ->orWhere('balance_den', '!=', 0)
                  ->orWhere('balance_cny', '!=', 0);
            })
            ->get(['id', 'name', 'balance_usd', 'balance_eur', 'balance_den', 'balance_cny']);

        $ids = $entities->pluck('id')->all();
        $lastActivity = [];
        if (!empty($ids)) {
            $rows = DB::table($txTable)
                ->whereIn($fkColumn, $ids)
                ->select($fkColumn . ' as eid', DB::raw('MAX(created_date) as last_date'))
                ->groupBy($fkColumn)->get();
            foreach ($rows as $r) {
                $lastActivity[$r->eid] = $r->last_date;
            }
        }

        $buckets = ['current' => $lang->write('aging.bucket.current'), 'b31_60' => $lang->write('aging.bucket.31_60'), 'b61_90' => $lang->write('aging.bucket.61_90'), 'b91_180' => $lang->write('aging.bucket.91_180'), 'b180_plus' => $lang->write('aging.bucket.180_plus')];
        $prepaid    = ['rows' => [], 'totals' => []];
        $payable    = ['rows' => [], 'totals' => []];
        foreach ($this->currencies as $c) {
            $prepaid['totals'][$c] = array_fill_keys(array_keys($buckets), 0.0);
            $payable['totals'][$c] = array_fill_keys(array_keys($buckets), 0.0);
        }

        foreach ($entities as $e) {
            $lastDate = $lastActivity[$e->id] ?? null;
            $days = $lastDate ? (int) round((strtotime($today) - strtotime($lastDate)) / 86400) : 9999;
            $bucket = $days <= 30 ? 'current'
                : ($days <= 60 ? 'b31_60'
                : ($days <= 90 ? 'b61_90'
                : ($days <= 180 ? 'b91_180' : 'b180_plus')));

            $prepaidBal = []; $payableBal = [];
            $hasPrepaid = false; $hasPayable = false;
            foreach ($this->currencies as $c) {
                $v = (float) $e->{'balance_' . $c};
                // Convention: positive = we paid them = prepaid (asset).
                //             negative = we owe them = AP (liability).
                if ($v > 0) { $prepaidBal[$c] = $v;  $hasPrepaid = true; } else { $prepaidBal[$c] = 0.0; }
                if ($v < 0) { $payableBal[$c] = -$v; $hasPayable = true; } else { $payableBal[$c] = 0.0; }
            }
            $base = [
                'id'        => $e->id, 'name' => $e->name,
                'last_date' => $lastDate, 'days' => $days, 'bucket' => $bucket,
            ];
            if ($hasPrepaid) {
                $prepaid['rows'][] = $base + ['balances' => $prepaidBal];
                foreach ($this->currencies as $c) $prepaid['totals'][$c][$bucket] += $prepaidBal[$c];
            }
            if ($hasPayable) {
                $payable['rows'][] = $base + ['balances' => $payableBal];
                foreach ($this->currencies as $c) $payable['totals'][$c][$bucket] += $payableBal[$c];
            }
        }
        usort($prepaid['rows'], fn($a, $b) => $b['days'] <=> $a['days']);
        usort($payable['rows'], fn($a, $b) => $b['days'] <=> $a['days']);

        $lang = new langController();
        $data = new dataController();
        return view('pages.accounting.entity_aging', [
            'entityKind' => $entityKind,
            'prepaid'    => $prepaid,
            'payable'    => $payable,
            'buckets'    => $buckets,
            'currencies' => $this->currencies,
            'data'       => $data,
            'lang'       => $lang,
            'section'    => 'accounting',
            'page'       => $entityKind . '_aging',
        ]);
    }

    /* ============================================================
     *  Profit & Loss statement (PDF)
     *  GET /accounting/profit-loss?from=YYYY-MM-DD&to=YYYY-MM-DD
     *
     *  Sourced from journal_lines via journalController::activityBetween().
     *  Every revenue/expense account in chart_of_accounts is iterated in
     *  code order, so a new account starts appearing here the moment it
     *  gets its first journal posting — no controller change needed.
     * ============================================================ */
    public function pnlStatement(Request $request)
    {
        $this->requireAdmin();
        $from = $request->get('from', date('Y-m-01'));
        $to   = $request->get('to',   date('Y-m-t'));

        $activity = (new journalController())->activityBetween($from, $to);

        $accounts = DB::table('chart_of_accounts')
            ->where('is_active', true)
            ->whereIn('type', ['revenue', 'expense'])
            ->orderBy('code')
            ->get();

        $revenue  = [];
        $expenses = [];
        $totals = ['revenue' => array_fill_keys($this->currencies, 0.0),
                   'expense' => array_fill_keys($this->currencies, 0.0),
                   'net'     => array_fill_keys($this->currencies, 0.0)];

        foreach ($accounts as $a) {
            // signed_net is dr-cr. Revenue (credit-normal) reads as
            // -signed_net; expense (debit-normal) reads as +signed_net.
            // Apply the sign once here so the view stays dumb.
            $sign    = $a->normal_balance === 'debit' ? 1 : -1;
            $amounts = array_fill_keys($this->currencies, 0.0);
            foreach ($this->currencies as $c) {
                $amounts[$c] = $sign * (float) ($activity[$a->code][$c] ?? 0);
            }
            $row = ['code' => $a->code, 'label' => $a->name, 'amounts' => $amounts];
            if ($a->type === 'revenue') {
                $revenue[] = $row;
                foreach ($this->currencies as $c) $totals['revenue'][$c] += $amounts[$c];
            } else {
                $expenses[] = $row;
                foreach ($this->currencies as $c) $totals['expense'][$c] += $amounts[$c];
            }
        }
        foreach ($this->currencies as $c) {
            $totals['net'][$c] = $totals['revenue'][$c] - $totals['expense'][$c];
        }

        $settings = (new settingsController())->get();
        $lang = new langController();
        $data = new dataController();
        $html = view('pages.accounting.pnl_pdf', compact(
            'from', 'to', 'revenue', 'expenses', 'totals',
            'settings', 'lang', 'data'
        ))->render();

        $isRtl = (auth()->user()->lang ?? 'en') === 'ar';
        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'default_font'   => 'dejavusans',
            'directionality' => $isRtl ? 'rtl' : 'ltr',
            'margin_top'     => 10, 'margin_bottom' => 10,
            'margin_left'    => 14, 'margin_right' => 14,
        ]);
        $mpdf->WriteHTML($html);
        $filename = 'pnl-' . $from . '-' . $to . '.pdf';
        return response($mpdf->Output($filename, 'I'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /* ============================================================
     *  Balance Sheet (PDF)
     *  GET /accounting/balance-sheet?as_of=YYYY-MM-DD
     *
     *  Sourced from journal_lines via journalController::balancesAsOf().
     *  Asset / liability / equity rows iterate every active account in
     *  chart_of_accounts in code order. Net income (YTD) is derived from
     *  revenue/expense activity Jan 1 → asOf and shown as a separate
     *  equity line. Owner's equity stays a balancing plug — once retained
     *  earnings get a real account it can be retired.
     * ============================================================ */
    public function balanceSheet(Request $request)
    {
        $this->requireAdmin();
        $asOf      = $request->get('as_of', date('Y-m-d'));
        $yearStart = date('Y-01-01', strtotime($asOf));

        $journal  = new journalController();
        $balances = $journal->balancesAsOf($asOf);
        $ytd      = $journal->activityBetween($yearStart, $asOf);

        $accounts = DB::table('chart_of_accounts')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $assets      = [];
        $liabilities = [];
        $equityRows  = [];   // every equity account except code 3000 (plug)
        $totals      = array_fill_keys(['assets', 'liabilities', 'equity'], array_fill_keys($this->currencies, 0.0));

        foreach ($accounts as $a) {
            // signed_net = SUM(dr) - SUM(cr). Convert to natural direction
            // by sign-flipping for credit-normal accounts.
            $sign    = $a->normal_balance === 'debit' ? 1 : -1;
            $amounts = array_fill_keys($this->currencies, 0.0);
            foreach ($this->currencies as $c) {
                $amounts[$c] = $sign * (float) ($balances[$a->code][$c] ?? 0);
            }
            $row = ['code' => $a->code, 'label' => $a->name, 'amounts' => $amounts];

            if ($a->type === 'asset') {
                $assets[] = $row;
                foreach ($this->currencies as $c) $totals['assets'][$c] += $amounts[$c];
            } elseif ($a->type === 'liability') {
                $liabilities[] = $row;
                foreach ($this->currencies as $c) $totals['liabilities'][$c] += $amounts[$c];
            } elseif ($a->type === 'equity' && $a->code !== '3000') {
                // Code 3000 = Owner's equity plug, computed below.
                $equityRows[] = $row;
                foreach ($this->currencies as $c) $totals['equity'][$c] += $amounts[$c];
            }
        }

        // Net income YTD = (revenue) - (expense), each in natural direction.
        $netIncome = array_fill_keys($this->currencies, 0.0);
        foreach ($accounts as $a) {
            if ($a->type !== 'revenue' && $a->type !== 'expense') continue;
            $sign = $a->normal_balance === 'debit' ? 1 : -1;
            foreach ($this->currencies as $c) {
                $val = $sign * (float) ($ytd[$a->code][$c] ?? 0);
                if ($a->type === 'revenue') $netIncome[$c] += $val;
                else                        $netIncome[$c] -= $val;
            }
        }

        // Owner's Equity now comes directly from the journal (code 3000) —
        // not synthesized as a plug. Letting the report tie by construction
        // hid genuine drift: a swallowed journal post or an unclosed period
        // would silently inflate equity instead of surfacing as an
        // imbalance. We compute the real balance here and surface any
        // residual as $imbalance for the view to render as a red banner.
        // Net income remains a separate line until a formal Retained
        // Earnings closing entry rolls it into equity at year-end.
        $ownersEquity = array_fill_keys($this->currencies, 0.0);
        $eq3000 = $accounts->firstWhere('code', '3000');
        if ($eq3000) {
            $sign = $eq3000->normal_balance === 'debit' ? 1 : -1;
            foreach ($this->currencies as $c) {
                $ownersEquity[$c] = $sign * (float) ($balances['3000'][$c] ?? 0);
            }
        }
        // Roll Owner's Equity + YTD net income into the equity total so the
        // view's subtotal row matches what's rendered.
        foreach ($this->currencies as $c) {
            $totals['equity'][$c] += $ownersEquity[$c] + $netIncome[$c];
        }

        // Imbalance check. A == L + E should hold for any internally
        // consistent journal. If it doesn't, we display the gap rather than
        // hide it. A 0.005 floor swallows float noise without missing real
        // drift.
        $imbalance    = array_fill_keys($this->currencies, 0.0);
        $hasImbalance = false;
        foreach ($this->currencies as $c) {
            $imbalance[$c] = round(
                $totals['assets'][$c] - $totals['liabilities'][$c] - $totals['equity'][$c],
                2
            );
            if (abs($imbalance[$c]) > 0.005) {
                $hasImbalance = true;
            }
        }

        $settings = (new settingsController())->get();
        $lang = new langController();
        $data = new dataController();
        $html = view('pages.accounting.balance_sheet_pdf', compact(
            'asOf', 'assets', 'liabilities', 'equityRows',
            'totals', 'netIncome', 'ownersEquity',
            'imbalance', 'hasImbalance',
            'settings', 'lang', 'data'
        ))->render();

        $isRtl = (auth()->user()->lang ?? 'en') === 'ar';
        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'default_font'   => 'dejavusans',
            'directionality' => $isRtl ? 'rtl' : 'ltr',
            'margin_top'     => 10, 'margin_bottom' => 10,
            'margin_left'    => 14, 'margin_right' => 14,
        ]);
        $mpdf->WriteHTML($html);
        $filename = 'balance-sheet-' . $asOf . '.pdf';
        return response($mpdf->Output($filename, 'I'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /* ============================================================
     *  Drift detector — journal vs entity trial balance
     *  GET /accounting/drift
     *
     *  For every chart_of_accounts row that has a derivation_key, fetch
     *    journal_net  = SUM(dr) - SUM(cr) on that account_code from
     *                   journal_lines (open entries only)
     *    entity_net   = deriveAccountBalances()[derivation_key] adjusted
     *                   for the account's normal balance side
     *  and compare. Anything off by more than 0.0001 is flagged.
     *
     *  The expected steady state once every mutation posts a journal is
     *  zero drift across the board. While the journal layer is still being
     *  rolled out (and for historical transactions that pre-date it), drift
     *  rows are the worklist — fix the wiring or backfill, then this page
     *  goes green.
     * ============================================================ */
    public function driftReport(Request $request)
    {
        $this->requireAdmin();
        $asOf = $request->get('as_of', date('Y-m-d'));

        // Entity-derived per-account balances. We use a window from the
        // beginning of the year through asOf so that P&L accounts get their
        // YTD activity captured the same way the trial balance does.
        $yearStart = date('Y-01-01', strtotime($asOf));
        $entity    = $this->deriveAccountBalances($yearStart, $asOf);

        // Journal-derived per-account balances. signed_net = DR - CR.
        // No status filter: both halves of a reversal pair are included so
        // they cancel arithmetically (see journalController::balancesAsOf
        // for the full explanation). Entity balances already reflect net-
        // of-reversal state, so this is what makes the drift comparison fair.
        $journalRows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.entry_id')
            ->where('journal_entries.entry_date', '<=', $asOf)
            ->select(
                'journal_lines.account_code',
                'journal_lines.currency',
                DB::raw('SUM(journal_lines.dr) as dr_total'),
                DB::raw('SUM(journal_lines.cr) as cr_total')
            )
            ->groupBy('journal_lines.account_code', 'journal_lines.currency')
            ->get();
        $journalByCode = [];
        foreach ($journalRows as $r) {
            $journalByCode[$r->account_code][$r->currency] = (float) $r->dr_total - (float) $r->cr_total;
        }

        $accounts = DB::table('chart_of_accounts')
            ->where('is_active', true)
            ->orderBy('code')->get();

        $rows = [];
        $driftCount = 0;
        $driftCurrencies = array_fill_keys($this->currencies, 0.0);
        foreach ($accounts as $a) {
            $entityByCcy = array_fill_keys($this->currencies, 0.0);
            if (!empty($a->derivation_key) && isset($entity[$a->derivation_key])) {
                // Entity values are stored as positive "natural-direction"
                // amounts. Sign-flip if the account's normal balance is
                // credit, so we end up with a signed (DR - CR) figure
                // comparable to the journal column.
                $sign = $a->normal_balance === 'debit' ? 1 : -1;
                foreach ($this->currencies as $c) {
                    $entityByCcy[$c] = $sign * (float) ($entity[$a->derivation_key][$c] ?? 0);
                }
            }
            $journalByCcy = array_fill_keys($this->currencies, 0.0);
            if (isset($journalByCode[$a->code])) {
                foreach ($this->currencies as $c) {
                    $journalByCcy[$c] = (float) ($journalByCode[$a->code][$c] ?? 0);
                }
            }
            $driftByCcy = [];
            $rowHasDrift = false;
            foreach ($this->currencies as $c) {
                $d = $journalByCcy[$c] - $entityByCcy[$c];
                $driftByCcy[$c] = $d;
                if (abs($d) > 0.0001) {
                    $rowHasDrift = true;
                    $driftCurrencies[$c] += $d;
                }
            }
            if ($rowHasDrift) $driftCount++;
            $rows[] = [
                'code'         => $a->code,
                'name'         => $a->name,
                'type'         => $a->type,
                'normal'       => $a->normal_balance,
                'has_key'      => !empty($a->derivation_key),
                'journal'      => $journalByCcy,
                'entity'       => $entityByCcy,
                'drift'        => $driftByCcy,
                'has_drift'    => $rowHasDrift,
            ];
        }

        $lang = new langController();
        $data = new dataController();
        return view('pages.accounting.drift', [
            'rows'             => $rows,
            'currencies'       => $this->currencies,
            'driftCount'       => $driftCount,
            'driftCurrencies'  => $driftCurrencies,
            'asOf'             => $asOf,
            'lang'             => $lang,
            'data'             => $data,
            'section'          => 'accounting',
            'page'             => 'drift',
        ]);
    }

    /* ============================================================
     *  AR / AP Client Aging
     *  GET /accounting/ar-aging
     *
     *  Two separate sections:
     *    - Receivables (clients with NEGATIVE balance — they owe us)
     *    - Client deposits we hold (positive balance — we owe them service)
     *
     *  Days bucket = days since the client's last transaction.
     * ============================================================ */
    public function arAging(Request $request)
    {
        $this->requireAdmin();
        $lang = new langController();
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

        $buckets = ['current' => $lang->write('aging.bucket.current'), 'b31_60' => $lang->write('aging.bucket.31_60'), 'b61_90' => $lang->write('aging.bucket.61_90'), 'b91_180' => $lang->write('aging.bucket.91_180'), 'b180_plus' => $lang->write('aging.bucket.180_plus')];

        $receivables = ['rows' => [], 'totals' => []];
        $deposits    = ['rows' => [], 'totals' => []];
        foreach ($this->currencies as $c) {
            $receivables['totals'][$c] = array_fill_keys(array_keys($buckets), 0.0);
            $deposits['totals'][$c]    = array_fill_keys(array_keys($buckets), 0.0);
        }

        foreach ($clients as $cl) {
            $lastDate = $lastActivity[$cl->id] ?? null;
            $days = $lastDate ? (int) round((strtotime($today) - strtotime($lastDate)) / 86400) : 9999;
            $bucket = $days <= 30 ? 'current'
                : ($days <= 60 ? 'b31_60'
                : ($days <= 90 ? 'b61_90'
                : ($days <= 180 ? 'b91_180' : 'b180_plus')));

            $negBalances = []; $posBalances = [];
            $hasNeg = false; $hasPos = false;
            foreach ($this->currencies as $c) {
                $v = (float) $cl->{'balance_' . $c};
                if ($v < 0) { $negBalances[$c] = -$v; $hasNeg = true; }
                else        { $negBalances[$c] = 0.0; }
                if ($v > 0) { $posBalances[$c] = $v;  $hasPos = true; }
                else        { $posBalances[$c] = 0.0; }
            }
            $base = [
                'id'        => $cl->id, 'code' => $cl->code, 'name' => $cl->name,
                'last_date' => $lastDate, 'days' => $days, 'bucket' => $bucket,
            ];
            if ($hasNeg) {
                $receivables['rows'][] = $base + ['balances' => $negBalances];
                foreach ($this->currencies as $c) $receivables['totals'][$c][$bucket] += $negBalances[$c];
            }
            if ($hasPos) {
                $deposits['rows'][] = $base + ['balances' => $posBalances];
                foreach ($this->currencies as $c) $deposits['totals'][$c][$bucket] += $posBalances[$c];
            }
        }

        usort($receivables['rows'], fn($a, $b) => $b['days'] <=> $a['days']);
        usort($deposits['rows'],    fn($a, $b) => $b['days'] <=> $a['days']);

        $dataController = new dataController();
        $lang = new langController();
        return view('pages.accounting.ar_aging', [
            'receivables'  => $receivables,
            'deposits'     => $deposits,
            'buckets'      => $buckets,
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

        // Post the variance to BOTH the per-branch ledger and the canonical
        // cash ledger so the daily journal, cash flow, trial balance, and
        // branch.balance_* all agree. Wrap in a transaction so a half-state
        // (one ledger updated, the other not) is impossible.
        $user      = auth()->user();
        $isOver    = $cc->variance > 0;
        $plusMinus = $isOver ? 'plus' : 'minus';
        $type      = $isOver ? 'deposit' : 'withdraw';
        $purpose   = $isOver ? 'cash_count_over' : 'cash_count_short';
        $value     = abs((float) $cc->variance);

        $dataController = new dataController();
        $txnNumber = $dataController->transaction_number('cash_count_adj', $cc->id);
        $autoId    = ((int) DB::table('branches_transactions')->where('branch', $cc->branch_id)->max('auto_id')) + 1;

        // Double-entry journal lines: cash count adjustment.
        // Over   → Dr 1000 Cash on hand    | Cr 4000 Commission revenue (misc gain)
        // Short  → Dr 5000 Operating exp   | Cr 1000 Cash on hand        (misc loss)
        if ($isOver) {
            $journalLines = [
                ['account_code' => '1000', 'dr' => $value, 'cr' => 0,     'currency' => $cc->currency, 'branch_id' => $cc->branch_id, 'description' => 'Cash count overage'],
                ['account_code' => '4000', 'dr' => 0,      'cr' => $value, 'currency' => $cc->currency, 'branch_id' => $cc->branch_id, 'description' => 'Cash count overage (misc gain)'],
            ];
        } else {
            $journalLines = [
                ['account_code' => '5000', 'dr' => $value, 'cr' => 0,     'currency' => $cc->currency, 'branch_id' => $cc->branch_id, 'description' => 'Cash count shortage'],
                ['account_code' => '1000', 'dr' => 0,      'cr' => $value, 'currency' => $cc->currency, 'branch_id' => $cc->branch_id, 'description' => 'Cash count shortage'],
            ];
        }

        $branchTxnId = null;
        // The journal post must live INSIDE this transaction so a journal
        // failure rolls back the branches/treasury/cash_counts mutations too.
        DB::transaction(function () use ($cc, $id, $type, $plusMinus, $value, $purpose, $autoId, $txnNumber, $user, $isOver, $journalLines, &$branchTxnId) {
            $branchTxnId = DB::table('branches_transactions')->insertGetId([
                'type'         => $type,
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

            DB::table('treasury_transactions')->insert([
                'type'               => $type,
                'data'               => json_encode(['cash_count_id' => $cc->id]),
                'created_date'       => date('Y-m-d'),
                'created_time'       => date('H:i:s'),
                'created_by'         => $user->id,
                'value'              => $value,
                'currency'           => $cc->currency,
                'plus_minus'         => $plusMinus,
                'branch'             => $cc->branch_id,
                'notes'              => 'Cash count adjustment',
                'auto_id'            => $autoId,
                'transaction_number' => $txnNumber,
            ]);

            $col = 'balance_' . $cc->currency;
            if ($plusMinus === 'plus') {
                DB::table('branches')->where('id', $cc->branch_id)->increment($col, $value);
            } else {
                DB::table('branches')->where('id', $cc->branch_id)->decrement($col, $value);
            }

            DB::table('cash_counts')->where('id', $id)->update([
                'adjustment_posted'        => true,
                'adjustment_transaction_id'=> $branchTxnId,
            ]);

            (new \App\Http\Controllers\journalController())->record([
                'entry_date'         => date('Y-m-d'),
                'kind'               => $isOver ? 'cash_count_over' : 'cash_count_short',
                'description'        => 'Cash count variance ' . ($isOver ? '+' : '−') . $value . ' ' . strtoupper($cc->currency),
                'source_table'       => 'cash_counts',
                'source_id'          => $id,
                'transaction_number' => $txnNumber,
                'branch_id'          => $cc->branch_id,
                'lines'              => $journalLines,
            ]);
        });

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
            ->where('clients_transactions.plus_minus', 'plus')
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
        if (!$txn || $txn->type !== 'deposit' || $txn->plus_minus !== 'plus') {
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
     *  Treasury by branch (HTML)
     *  GET /accounting/treasury-by-branch
     *
     *  Cross-tab of every branch's cash balance per currency, with:
     *   - branches.balance_* as the live treasury figure
     *   - the most recent cash_counts row per (branch, currency) so the
     *     user can see when each pile was last reconciled and any drift
     *   - a USD-equivalent column using the live FX table, so the owner
     *     can answer "كل فرع كم عنده فلوس؟" with one number per branch
     *   - a totals row across all branches
     * ============================================================ */
    public function treasuryByBranch(Request $request)
    {
        $this->requireAdmin();

        $dataController = new dataController();
        $rates          = $dataController->currency_exchange_rates;   // foreign per 1 USD

        $branches = DB::table('branches')->where('deleted', 0)->orderBy('id')->get();

        // Pull the most recent cash_count per (branch_id, currency) in one query.
        // We need the LATEST id per (branch_id, currency) — MySQL prior to 8.0
        // can't do window functions cleanly, so we lean on a self-join via
        // grouped max(id).
        $latestIds = DB::table('cash_counts')
            ->select('branch_id', 'currency', DB::raw('MAX(id) as latest_id'))
            ->groupBy('branch_id', 'currency')
            ->pluck('latest_id');

        $latestCounts = DB::table('cash_counts')
            ->whereIn('id', $latestIds)
            ->get()
            ->keyBy(function ($r) { return $r->branch_id . '|' . $r->currency; });

        $totals = array_fill_keys($this->currencies, 0.0);
        $totalsUsd = 0.0;
        $rows = [];

        foreach ($branches as $b) {
            $balances    = [];
            $countInfo   = [];
            $branchUsd   = 0.0;

            foreach ($this->currencies as $c) {
                $bal = (float) ($b->{'balance_' . $c} ?? 0);
                $balances[$c] = $bal;
                $totals[$c] += $bal;

                $usdEq = $c === 'usd'
                    ? $bal
                    : (!empty($rates[$c]) && (float) $rates[$c] > 0 ? $bal / (float) $rates[$c] : 0.0);
                $branchUsd += $usdEq;

                $key = $b->id . '|' . $c;
                $cc  = $latestCounts->get($key);
                $countInfo[$c] = $cc ? [
                    'count_date' => $cc->count_date,
                    'variance'   => (float) $cc->variance,
                ] : null;
            }
            $totalsUsd += $branchUsd;

            $rows[] = [
                'branch'      => $b,
                'balances'    => $balances,
                'countInfo'   => $countInfo,
                'branch_usd'  => $branchUsd,
            ];
        }

        $lang = new langController();
        return view('pages.accounting.treasury_by_branch', [
            'rows'         => $rows,
            'currencies'   => $this->currencies,
            'totals'       => $totals,
            'totals_usd'   => $totalsUsd,
            'rates'        => $rates,
            'lang'         => $lang,
            'data'         => $dataController,
            'section'      => 'accounting',
            'page'         => 'treasury_by_branch',
        ]);
    }

    /* ============================================================
     *  Revenue by Service — group revenue accounts by code over a
     *  date range. Surfaces "كم صافي ربح الشركة من كل خدمة" without
     *  making the operator pivot the trial balance by hand.
     *
     *  Looks at all 4xxx accounts except 4200 (FX gain), which is
     *  reported separately at the bottom — it's not a service.
     * ============================================================ */
    public function revenueByService(Request $request)
    {
        $this->requireAdmin();

        [$from, $to, $rangeLabel] = $this->resolveDateRange($request);
        $lang = new langController();

        // Revenue = sum(cr) - sum(dr) per (account_code, currency).
        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.entry_id')
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->where('journal_lines.account_code', 'like', '4%')
            ->where('journal_lines.account_code', '!=', '4200')
            ->select(
                'journal_lines.account_code',
                'journal_lines.account_name',
                'journal_lines.currency',
                DB::raw('SUM(cr) - SUM(dr) AS amount'),
            )
            ->groupBy('journal_lines.account_code', 'journal_lines.account_name', 'journal_lines.currency')
            ->orderBy('journal_lines.account_code')
            ->get();

        $services = [];
        foreach ($rows as $r) {
            $key = $r->account_code . ' — ' . $r->account_name;
            $services[$key]['code'] = $r->account_code;
            $services[$key]['name'] = $r->account_name;
            $services[$key]['by_currency'][$r->currency] = (float) $r->amount;
        }

        // FX gain reported separately.
        $fxGain = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.entry_id')
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->where('journal_lines.account_code', '4200')
            ->select('journal_lines.currency', DB::raw('SUM(cr) - SUM(dr) AS amount'))
            ->groupBy('journal_lines.currency')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->currency => (float) $r->amount])
            ->all();

        return view('pages.accounting.revenue_by_service', [
            'services'    => $services,
            'fx_gain'     => $fxGain,
            'currencies'  => $this->currencies,
            'from'        => $from,
            'to'          => $to,
            'range_label' => $rangeLabel,
            'lang'        => $lang,
            'section'     => 'accounting',
            'page'        => 'revenue_by_service',
        ]);
    }

    /* ============================================================
     *  Revenue by Branch — same pivot but grouped by branch_id.
     * ============================================================ */
    public function revenueByBranch(Request $request)
    {
        $this->requireAdmin();
        [$from, $to, $rangeLabel] = $this->resolveDateRange($request);
        return $this->byBranchReport($request, $from, $to, $rangeLabel, '4%', 'cr', 'revenue_by_branch');
    }

    /* ============================================================
     *  Expense by Branch — mirrors revenueByBranch over 5xxx.
     * ============================================================ */
    public function expenseByBranch(Request $request)
    {
        $this->requireAdmin();
        [$from, $to, $rangeLabel] = $this->resolveDateRange($request);
        return $this->byBranchReport($request, $from, $to, $rangeLabel, '5%', 'dr', 'expense_by_branch');
    }

    /** Shared implementation for the two by-branch reports. */
    private function byBranchReport(Request $request, string $from, string $to, string $rangeLabel, string $codePattern, string $normalSide, string $page)
    {
        $lang = new langController();

        $amountExpr = $normalSide === 'cr'
            ? 'SUM(cr) - SUM(dr)'
            : 'SUM(dr) - SUM(cr)';

        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.entry_id')
            ->leftJoin('branches', 'branches.id', '=', 'journal_lines.branch_id')
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->where('journal_lines.account_code', 'like', $codePattern)
            ->select(
                'journal_lines.branch_id',
                'branches.name as branch_name',
                'journal_lines.currency',
                DB::raw("{$amountExpr} AS amount"),
            )
            ->groupBy('journal_lines.branch_id', 'branches.name', 'journal_lines.currency')
            ->orderByRaw('CASE WHEN journal_lines.branch_id IS NULL THEN 1 ELSE 0 END, branches.name')
            ->get();

        $branches = [];
        foreach ($rows as $r) {
            $key  = $r->branch_id ?? '__unassigned';
            $name = $r->branch_name ?? '(no branch)';
            $branches[$key]['id']   = $r->branch_id;
            $branches[$key]['name'] = $name;
            $branches[$key]['by_currency'][$r->currency] = (float) $r->amount;
        }

        return view("pages.accounting.{$page}", [
            'branches'    => $branches,
            'currencies'  => $this->currencies,
            'from'        => $from,
            'to'          => $to,
            'range_label' => $rangeLabel,
            'lang'        => $lang,
            'section'     => 'accounting',
            'page'        => $page,
        ]);
    }

    /**
     * Parse ?from=YYYY-MM-DD&to=YYYY-MM-DD with a sensible default of
     * "this month so far." Returns [from, to, human_label].
     */
    private function resolveDateRange(Request $request): array
    {
        $from = $request->query('from') ?: date('Y-m-01');
        $to   = $request->query('to')   ?: date('Y-m-d');
        $label = ($from === date('Y-m-01') && $to === date('Y-m-d'))
            ? 'Month to date'
            : "{$from} → {$to}";
        return [$from, $to, $label];
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
