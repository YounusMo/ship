<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class journalController extends Controller
{
    private array $currencies = ['usd', 'eur', 'den', 'cny'];

    /* ============================================================
     *  Core API — record(), reverse()
     * ============================================================ */

    /**
     * Record a balanced journal entry.
     *
     *   $entry = [
     *     'entry_date'   => 'YYYY-MM-DD',
     *     'kind'         => 'client_deposit' | 'client_withdraw' | ...
     *     'description'  => '...',
     *     'source_table' => 'clients_transactions' | null,
     *     'source_id'    => int | null,
     *     'transaction_number' => '...' | null,
     *     'branch_id'    => int | null,
     *     'lines'        => [
     *        ['account_code' => '1000', 'dr' => 250, 'cr' => 0, 'currency' => 'usd',
     *         'counterparty_type' => 'client', 'counterparty_id' => 162, 'description' => '...'],
     *        ['account_code' => '2000', 'dr' => 0,   'cr' => 250, 'currency' => 'usd',
     *         'counterparty_type' => 'client', 'counterparty_id' => 162],
     *     ],
     *   ]
     *
     * Invariant enforced: per-currency SUM(dr) == SUM(cr). Throws if violated.
     *
     * Period guard: the entry_date must fall in an open accounting period
     * (today is also checked, per assertPeriodOpen semantics). This guard
     * lives here — not at every call site — so anyone posting a journal
     * entry from any code path (controller, command, future caller) cannot
     * accidentally write into a closed month. Backfill of historical rows
     * passes $enforcePeriod=false; nothing else should.
     *
     * Returns the new entry_id.
     */
    public function record(array $entry, bool $enforcePeriod = true): int
    {
        if (empty($entry['lines']) || !is_array($entry['lines']) || count($entry['lines']) < 2) {
            throw new \InvalidArgumentException('journal entry requires >=2 lines');
        }

        if ($enforcePeriod) {
            $this->assertPeriodOpen($entry['entry_date'] ?? date('Y-m-d'));
        }

        // Per-currency balance check.
        $totals = [];
        foreach ($entry['lines'] as $i => $line) {
            $ccy = $line['currency'] ?? null;
            if (!$ccy) throw new \InvalidArgumentException("line $i missing currency");
            $totals[$ccy] = $totals[$ccy] ?? ['dr' => 0.0, 'cr' => 0.0];
            $totals[$ccy]['dr'] += (float) ($line['dr'] ?? 0);
            $totals[$ccy]['cr'] += (float) ($line['cr'] ?? 0);
        }
        foreach ($totals as $ccy => $t) {
            if (abs($t['dr'] - $t['cr']) > 0.0001) {
                throw new \InvalidArgumentException(
                    "journal entry unbalanced for $ccy: dr={$t['dr']} cr={$t['cr']}"
                );
            }
        }

        // Resolve account ids + names from code.
        $codes = array_column($entry['lines'], 'account_code');
        $codes = array_filter(array_unique($codes));
        $accounts = DB::table('chart_of_accounts')->whereIn('code', $codes)->get()->keyBy('code');
        foreach ($codes as $c) {
            if (!isset($accounts[$c])) {
                throw new \InvalidArgumentException("account code $c not in chart_of_accounts");
            }
        }

        $user = auth()->user();
        $entryId = null;

        DB::transaction(function () use ($entry, $accounts, $user, &$entryId) {
            $entryId = DB::table('journal_entries')->insertGetId([
                'entry_date'         => $entry['entry_date'] ?? date('Y-m-d'),
                'posted_at'          => date('Y-m-d H:i:s'),
                'posted_by_user_id'  => $user?->id,
                'posted_by_user_name'=> $user?->name,
                'kind'               => $entry['kind'] ?? 'manual',
                'description'        => mb_substr((string) ($entry['description'] ?? ''), 0, 500),
                'source_table'       => $entry['source_table'] ?? null,
                'source_id'          => $entry['source_id'] ?? null,
                'transaction_number' => $entry['transaction_number'] ?? null,
                'branch_id'          => $entry['branch_id'] ?? null,
                'status'             => 'open',
                'created_at'         => date('Y-m-d H:i:s'),
                'updated_at'         => date('Y-m-d H:i:s'),
            ]);

            $lineNo = 0;
            foreach ($entry['lines'] as $line) {
                $lineNo++;
                $a = $accounts[$line['account_code']];
                DB::table('journal_lines')->insert([
                    'entry_id'          => $entryId,
                    'line_no'           => $lineNo,
                    'account_id'        => $a->id,
                    'account_code'      => $a->code,
                    'account_name'      => $a->name,
                    'dr'                => (float) ($line['dr'] ?? 0),
                    'cr'                => (float) ($line['cr'] ?? 0),
                    'currency'          => $line['currency'],
                    'description'       => mb_substr((string) ($line['description'] ?? ''), 0, 500),
                    'counterparty_type' => $line['counterparty_type'] ?? null,
                    'counterparty_id'   => $line['counterparty_id'] ?? null,
                    'branch_id'         => $line['branch_id'] ?? ($entry['branch_id'] ?? null),
                    'created_at'        => date('Y-m-d H:i:s'),
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);
            }
        });

        return $entryId;
    }

    /**
     * Append a reverse entry that cancels the given entry. Used for
     * corrections — keeps the original row intact (append-only) but
     * neutralizes its effect on the trial balance.
     */
    public function reverse(int $entryId, ?string $description = null): int
    {
        $orig = DB::table('journal_entries')->where('id', $entryId)->first();
        if (!$orig) throw new \InvalidArgumentException("entry $entryId not found");
        if ($orig->status === 'reversed') throw new \InvalidArgumentException("entry $entryId already reversed");

        $lines = DB::table('journal_lines')->where('entry_id', $entryId)->orderBy('line_no')->get();

        $reverseLines = [];
        foreach ($lines as $l) {
            // Swap dr and cr to cancel.
            $reverseLines[] = [
                'account_code'      => $l->account_code,
                'dr'                => (float) $l->cr,
                'cr'                => (float) $l->dr,
                'currency'          => $l->currency,
                'counterparty_type' => $l->counterparty_type,
                'counterparty_id'   => $l->counterparty_id,
                'branch_id'         => $l->branch_id,
                'description'       => 'Reverses #' . $orig->id,
            ];
        }
        $reverseId = $this->record([
            'entry_date'         => date('Y-m-d'),
            'kind'               => 'reversal',
            'description'        => $description ?? ('Reversal of #' . $orig->id),
            'source_table'       => $orig->source_table,
            'source_id'          => $orig->source_id,
            'transaction_number' => $orig->transaction_number,
            'branch_id'          => $orig->branch_id,
            'lines'              => $reverseLines,
        ]);

        DB::table('journal_entries')->where('id', $reverseId)->update(['reverses_entry_id' => $entryId]);
        DB::table('journal_entries')->where('id', $entryId)->update([
            'reversed_by_entry_id' => $reverseId,
            'status'               => 'reversed',
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);
        return $reverseId;
    }

    /* ============================================================
     *  Reports
     * ============================================================ */

    /**
     * Trial balance derived from journal_lines. Authoritative — by
     * construction it always balances (per the invariant).
     */
    public function trialBalanceView(Request $request)
    {
        $this->requireAdmin();
        $asOf = $request->get('as_of', date('Y-m-d'));

        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.entry_id')
            ->where('journal_entries.status', 'open')
            ->where('journal_entries.entry_date', '<=', $asOf)
            ->select(
                'journal_lines.account_code',
                'journal_lines.account_name',
                'journal_lines.currency',
                DB::raw('SUM(journal_lines.dr) as dr_total'),
                DB::raw('SUM(journal_lines.cr) as cr_total')
            )
            ->groupBy('journal_lines.account_code', 'journal_lines.account_name', 'journal_lines.currency')
            ->orderBy('journal_lines.account_code')
            ->get();

        // Pivot into [code => [name, balances per currency], totals]
        $accounts = [];
        $totals   = ['dr' => array_fill_keys($this->currencies, 0.0), 'cr' => array_fill_keys($this->currencies, 0.0)];
        foreach ($rows as $r) {
            $code = $r->account_code;
            $accounts[$code] = $accounts[$code] ?? [
                'code' => $code,
                'name' => $r->account_name,
                'dr'   => array_fill_keys($this->currencies, 0.0),
                'cr'   => array_fill_keys($this->currencies, 0.0),
            ];
            if (in_array($r->currency, $this->currencies, true)) {
                $accounts[$code]['dr'][$r->currency] += (float) $r->dr_total;
                $accounts[$code]['cr'][$r->currency] += (float) $r->cr_total;
                $totals['dr'][$r->currency] += (float) $r->dr_total;
                $totals['cr'][$r->currency] += (float) $r->cr_total;
            }
        }

        $lang = new langController();
        $data = new dataController();
        return view('pages.accounting.journal_trial_balance', [
            'accounts'   => array_values($accounts),
            'totals'     => $totals,
            'currencies' => $this->currencies,
            'asOf'       => $asOf,
            'lang'       => $lang,
            'data'       => $data,
            'section'    => 'accounting',
            'page'       => 'journal_trial_balance',
        ]);
    }

    /**
     * Journal entry browser. Newest first.
     */
    public function entriesIndex(Request $request)
    {
        $this->requireAdmin();
        $from = $request->get('from', date('Y-m-01'));
        $to   = $request->get('to',   date('Y-m-t'));

        $entries = DB::table('journal_entries')
            ->whereBetween('entry_date', [$from, $to])
            ->orderByDesc('id')
            ->limit(500)
            ->get();
        $entryIds = $entries->pluck('id')->all();
        $linesByEntry = [];
        if (!empty($entryIds)) {
            $lines = DB::table('journal_lines')
                ->whereIn('entry_id', $entryIds)
                ->orderBy('entry_id')
                ->orderBy('line_no')
                ->get();
            foreach ($lines as $l) {
                $linesByEntry[$l->entry_id][] = $l;
            }
        }

        $lang = new langController();
        $data = new dataController();
        return view('pages.accounting.journal_entries', [
            'entries'      => $entries,
            'linesByEntry' => $linesByEntry,
            'from'         => $from,
            'to'           => $to,
            'lang'         => $lang,
            'data'         => $data,
            'section'      => 'accounting',
            'page'         => 'journal_entries',
        ]);
    }

    public function entryShow($id)
    {
        $this->requireAdmin();
        $entry = DB::table('journal_entries')->where('id', $id)->first();
        if (!$entry) abort(404);
        $lines = DB::table('journal_lines')->where('entry_id', $id)->orderBy('line_no')->get();
        return response()->json([
            'entry' => $entry,
            'lines' => $lines,
        ]);
    }

    public function entryReverse(Request $request, $id)
    {
        $this->requireAdmin();
        try {
            $reverseId = $this->reverse((int) $id, $request->reason);
            return response()->json(['type' => 'success', 'reverse_entry_id' => $reverseId]);
        } catch (\Throwable $th) {
            return $this->reportException($th, 'journal reverse');
        }
    }

    private function requireAdmin(): void
    {
        $u = auth()->user();
        if (!$u || $u->type !== 'admin') {
            abort(403);
        }
    }
}
