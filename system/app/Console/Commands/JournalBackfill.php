<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\journalController;

/**
 * Replay historical ledger rows into the double-entry journal layer.
 *
 * Idempotent: a source row that already has a matching journal entry
 * (looked up by source_table + source_id OR by transaction_number) is
 * skipped, so this command is safe to run repeatedly.
 *
 * Mapping rules mirror the live mutation wiring in:
 *   - clientsController::deposit/withdraw/withdraw_commission/transfer_clients
 *   - clientsReportsController::approveReject  (currency transfer)
 *   - suppliersController::deposit
 *   - customsBrokersController::deposit
 *   - branchesController::deposit_branch/add_expenses
 *   - accountingController::cashCountAdjust
 *
 * Anything we can't classify falls into the "unknown" bucket and is
 * reported at the end. Those rows need manual handling — usually because
 * the type/purpose was custom or the row is old test data.
 */
class JournalBackfill extends Command
{
    protected $signature   = 'journal:backfill {--dry-run : Print plan, do not insert} {--limit=0 : Stop after N source rows for testing}';
    protected $description = 'Backfill journal_entries from historical ledger rows.';

    private journalController $journal;
    private array $stats = ['created' => 0, 'skipped_existing' => 0, 'skipped_pending' => 0, 'unknown' => 0, 'error' => 0];
    private array $unknownReasons = [];
    private bool $dry = false;
    private int $limit = 0;
    private int $processed = 0;

    public function handle(): int
    {
        $this->dry   = (bool) $this->option('dry-run');
        $this->limit = (int) $this->option('limit');
        $this->journal = new journalController();

        $this->info(($this->dry ? '[DRY RUN] ' : '') . 'Starting journal backfill…');

        DB::beginTransaction();
        try {
            $this->processClientTransactions();
            $this->processBranchTransactions();
            $this->processSupplierTransactions();
            $this->processBrokerTransactions();
            if ($this->dry) {
                DB::rollBack();
                $this->warn('Rolled back (dry run).');
            } else {
                DB::commit();
                $this->info('Committed.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Aborted: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. Created %d entries · Skipped existing %d · Skipped pending %d · Unknown %d · Errors %d',
            $this->stats['created'], $this->stats['skipped_existing'], $this->stats['skipped_pending'],
            $this->stats['unknown'], $this->stats['error']
        ));
        if (!empty($this->unknownReasons)) {
            $this->newLine();
            $this->warn('Unknown source rows (first 20):');
            foreach (array_slice($this->unknownReasons, 0, 20) as $r) {
                $this->line('  ' . $r);
            }
        }
        return 0;
    }

    /* ============================================================ */

    private function alreadyJournaled(string $table, ?int $sourceId, ?string $txn): bool
    {
        $q = DB::table('journal_entries')->where('status', 'open');
        if ($sourceId !== null && $sourceId > 0) {
            $exists = (clone $q)->where('source_table', $table)->where('source_id', $sourceId)->exists();
            if ($exists) return true;
        }
        if (!empty($txn)) {
            $exists = (clone $q)
                ->where('source_table', $table)
                ->where('transaction_number', $txn)->exists();
            if ($exists) return true;
        }
        return false;
    }

    private function tickLimit(): bool
    {
        $this->processed++;
        return $this->limit > 0 && $this->processed >= $this->limit;
    }

    private function record(array $entry, string $debugLabel): void
    {
        if ($this->dry) {
            $this->line(sprintf('  + %s   %s', $debugLabel, $entry['description'] ?? ''));
            $this->stats['created']++;
            return;
        }
        try {
            $this->journal->record($entry);
            $this->stats['created']++;
        } catch (\Throwable $e) {
            $this->stats['error']++;
            $this->error('  ! ' . $debugLabel . '  ' . $e->getMessage());
        }
    }

    /* ============================================================
     *  Clients
     * ============================================================ */
    private function processClientTransactions(): void
    {
        $this->info('-- clients_transactions');
        $rows = DB::table('clients_transactions')->orderBy('id')->get();
        // Group by transaction_number to detect c2c pairs (two rows that share
        // one txn number — one minus on the from-client, one plus on the to).
        $byTxn = $rows->groupBy('transaction_number');

        foreach ($byTxn as $txn => $group) {
            // Currency transfers are single-row with type='transfer'.
            // c2c pairs are TWO rows sharing one transaction_number where
            // the JSON `data` column carries from_client + to_client tags.
            $isCurrencyTransfer = $group->first()->type === 'transfer';
            $isC2C = false;
            if (!$isCurrencyTransfer && $group->count() >= 2 && $group->pluck('plus_minus')->unique()->count() === 2) {
                $d = json_decode((string) ($group->first()->data ?? ''));
                if ($d && (isset($d->from_client) || isset($d->to_client))) {
                    $isC2C = true;
                }
            }

            if ($isCurrencyTransfer) {
                foreach ($group as $r) {
                    $this->handleCurrencyTransfer($r);
                    if ($this->tickLimit()) return;
                }
                continue;
            }
            if ($isC2C) {
                $this->handleC2C($group->values()->all());
                if ($this->tickLimit()) return;
                continue;
            }
            // Single-row flows.
            foreach ($group as $r) {
                $this->handleSingleClientRow($r);
                if ($this->tickLimit()) return;
            }
        }
    }

    private function handleCurrencyTransfer(object $r): void
    {
        if ($this->alreadyJournaled('clients_transactions', (int) $r->id, $r->transaction_number)) {
            $this->stats['skipped_existing']++;
            return;
        }
        if (($r->status ?? '') === 'pending') {
            $this->stats['skipped_pending']++;
            return;
        }
        if (empty($r->to_currency) || empty($r->transfer_value)) {
            $this->stats['unknown']++;
            $this->unknownReasons[] = "clients_transactions#$r->id: transfer missing to_currency/transfer_value";
            return;
        }
        $this->record([
            'entry_date'         => $r->created_date,
            'kind'               => 'client_currency_transfer',
            'description'        => 'Currency transfer ' . $r->value . ' ' . strtoupper($r->currency) . ' → ' . $r->transfer_value . ' ' . strtoupper($r->to_currency),
            'source_table'       => 'clients_transactions',
            'source_id'          => (int) $r->id,
            'transaction_number' => $r->transaction_number,
            'branch_id'          => is_numeric($r->branch) ? (int) $r->branch : null,
            'lines'              => [
                ['account_code' => '2000', 'dr' => (float) $r->value,          'cr' => 0,                          'currency' => $r->currency,    'counterparty_type' => 'client', 'counterparty_id' => (int) $r->client_id],
                ['account_code' => '1000', 'dr' => 0,                          'cr' => (float) $r->value,          'currency' => $r->currency,    'counterparty_type' => 'client', 'counterparty_id' => (int) $r->client_id],
                ['account_code' => '1000', 'dr' => (float) $r->transfer_value, 'cr' => 0,                          'currency' => $r->to_currency, 'counterparty_type' => 'client', 'counterparty_id' => (int) $r->client_id],
                ['account_code' => '2000', 'dr' => 0,                          'cr' => (float) $r->transfer_value, 'currency' => $r->to_currency, 'counterparty_type' => 'client', 'counterparty_id' => (int) $r->client_id],
            ],
        ], "currency_transfer ct#$r->id");
    }

    private function handleC2C(array $group): void
    {
        $from = null; $to = null;
        foreach ($group as $r) {
            if (($r->plus_minus ?? '') === 'minus') $from = $r;
            elseif (($r->plus_minus ?? '') === 'plus')  $to   = $r;
        }
        if (!$from || !$to) {
            // Shouldn't happen if isC2C was true; fall back to single processing.
            foreach ($group as $r) $this->handleSingleClientRow($r);
            return;
        }
        if ($this->alreadyJournaled('clients_transactions', (int) $from->id, $from->transaction_number)) {
            $this->stats['skipped_existing']++;
            return;
        }
        $this->record([
            'entry_date'         => $from->created_date,
            'kind'               => 'client_to_client_transfer',
            'description'        => 'Transfer ' . $from->value . ' ' . strtoupper($from->currency) . ' between clients',
            'source_table'       => 'clients_transactions',
            'source_id'          => (int) $from->id,
            'transaction_number' => $from->transaction_number,
            'lines'              => [
                ['account_code' => '2000', 'dr' => (float) $from->value, 'cr' => 0, 'currency' => $from->currency,
                 'counterparty_type' => 'client', 'counterparty_id' => (int) $from->client_id, 'description' => 'Decrease from-client'],
                ['account_code' => '2000', 'dr' => 0, 'cr' => (float) $to->value, 'currency' => $to->currency,
                 'counterparty_type' => 'client', 'counterparty_id' => (int) $to->client_id, 'description' => 'Increase to-client'],
            ],
        ], "c2c ct#$from->id+$to->id");
    }

    private function handleSingleClientRow(object $r): void
    {
        if ($this->alreadyJournaled('clients_transactions', (int) $r->id, $r->transaction_number)) {
            $this->stats['skipped_existing']++;
            return;
        }
        if (($r->status ?? '') === 'pending') {
            $this->stats['skipped_pending']++;
            return;
        }
        $type = $r->type;
        $value = (float) $r->value;
        $cid = (int) $r->client_id;
        $ccy = $r->currency;
        $branch = is_numeric($r->branch) ? (int) $r->branch : null;
        $base = [
            'entry_date'         => $r->created_date,
            'source_table'       => 'clients_transactions',
            'source_id'          => (int) $r->id,
            'transaction_number' => $r->transaction_number,
            'branch_id'          => $branch,
        ];

        if (in_array($type, ['deposit', 'exp_deposit'], true) && $r->plus_minus === 'plus') {
            $this->record($base + [
                'kind'        => 'client_deposit',
                'description' => 'Client deposit ' . $value . ' ' . strtoupper($ccy),
                'lines'       => [
                    ['account_code' => '1000', 'dr' => $value, 'cr' => 0, 'currency' => $ccy, 'counterparty_type' => 'client', 'counterparty_id' => $cid, 'branch_id' => $branch],
                    ['account_code' => '2000', 'dr' => 0, 'cr' => $value, 'currency' => $ccy, 'counterparty_type' => 'client', 'counterparty_id' => $cid, 'branch_id' => $branch],
                ],
            ], "client_deposit ct#$r->id");
        } elseif (in_array($type, ['withdraw', 'exp_withdraw', 'exp_custom_withdraw'], true) && $r->plus_minus === 'minus') {
            $this->record($base + [
                'kind'        => 'client_withdraw',
                'description' => 'Client withdraw ' . $value . ' ' . strtoupper($ccy),
                'lines'       => [
                    ['account_code' => '2000', 'dr' => $value, 'cr' => 0, 'currency' => $ccy, 'counterparty_type' => 'client', 'counterparty_id' => $cid, 'branch_id' => $branch],
                    ['account_code' => '1000', 'dr' => 0, 'cr' => $value, 'currency' => $ccy, 'counterparty_type' => 'client', 'counterparty_id' => $cid, 'branch_id' => $branch],
                ],
            ], "client_withdraw ct#$r->id");
        } elseif ($type === 'withdraw_commission') {
            $this->record($base + [
                'kind'        => 'commission',
                'description' => 'Commission ' . $value . ' ' . strtoupper($ccy),
                'lines'       => [
                    ['account_code' => '2000', 'dr' => $value, 'cr' => 0, 'currency' => $ccy, 'counterparty_type' => 'client', 'counterparty_id' => $cid],
                    ['account_code' => '4000', 'dr' => 0, 'cr' => $value, 'currency' => $ccy, 'counterparty_type' => 'client', 'counterparty_id' => $cid],
                ],
            ], "commission ct#$r->id");
        } else {
            $this->stats['unknown']++;
            $this->unknownReasons[] = "clients_transactions#$r->id type=$type plus_minus=$r->plus_minus";
        }
    }

    /* ============================================================
     *  Branches (treasury)
     * ============================================================ */
    private function processBranchTransactions(): void
    {
        $this->info('-- branches_transactions');
        $rows = DB::table('branches_transactions')->orderBy('id')->get();
        // De-dup transfer_branch (one txn number → two rows: from-side minus,
        // to-side plus). One entry per pair.
        $seenTxn = [];

        foreach ($rows as $r) {
            if ($this->alreadyJournaled('branches_transactions', (int) $r->id, $r->transaction_number)) {
                $this->stats['skipped_existing']++;
                continue;
            }
            $type = $r->type;
            $purpose = $r->purpose;
            $value = (float) $r->value;
            $ccy = $r->currency;
            $branch = is_numeric($r->branch) ? (int) $r->branch : null;
            $base = [
                'entry_date'         => $r->created_date,
                'source_table'       => 'branches_transactions',
                'source_id'          => (int) $r->id,
                'transaction_number' => $r->transaction_number,
                'branch_id'          => $branch,
            ];

            // branch-to-branch transfer pair: handle once
            if ($type === 'withdraw' && $purpose === null && !empty($r->transaction_number)) {
                // ambiguous — fall through to default rules
            }

            if (in_array($type, ['branch_deposit', 'deposit'], true) && $r->plus_minus === 'plus') {
                // Cash count overage rows show up here with type=deposit and
                // purpose=cash_count_over — split them into the misc-gain
                // pattern so the trial balance attributes them properly.
                if ($purpose === 'cash_count_over') {
                    $this->record($base + [
                        'kind'        => 'cash_count_over',
                        'description' => 'Cash count overage ' . $value . ' ' . strtoupper($ccy),
                        'lines'       => [
                            ['account_code' => '1000', 'dr' => $value, 'cr' => 0, 'currency' => $ccy, 'branch_id' => $branch],
                            ['account_code' => '4000', 'dr' => 0, 'cr' => $value, 'currency' => $ccy, 'branch_id' => $branch],
                        ],
                    ], "cash_count_over bt#$r->id");
                } else {
                    // Default: cash injection / owner contribution → equity.
                    $this->record($base + [
                        'kind'        => 'branch_deposit',
                        'description' => 'Treasury deposit ' . $value . ' ' . strtoupper($ccy) . ($purpose ? ' (' . $purpose . ')' : ''),
                        'lines'       => [
                            ['account_code' => '1000', 'dr' => $value, 'cr' => 0, 'currency' => $ccy, 'branch_id' => $branch],
                            ['account_code' => '3000', 'dr' => 0, 'cr' => $value, 'currency' => $ccy, 'branch_id' => $branch],
                        ],
                    ], "branch_deposit bt#$r->id");
                }
            } elseif (in_array($type, ['expenses_branch', 'exp_withdraw', 'exp_custom_withdraw', 'branch_withdraw', 'withdraw'], true) && $r->plus_minus === 'minus') {
                if ($purpose === 'cash_count_short') {
                    $this->record($base + [
                        'kind'        => 'cash_count_short',
                        'description' => 'Cash count shortage ' . $value . ' ' . strtoupper($ccy),
                        'lines'       => [
                            ['account_code' => '5000', 'dr' => $value, 'cr' => 0, 'currency' => $ccy, 'branch_id' => $branch],
                            ['account_code' => '1000', 'dr' => 0, 'cr' => $value, 'currency' => $ccy, 'branch_id' => $branch],
                        ],
                    ], "cash_count_short bt#$r->id");
                } else {
                    $debitCode = '5000';
                    if ($purpose === 'owner_drawing')      $debitCode = '3100';
                    elseif ($purpose === 'owner_salary')   $debitCode = '5100';
                    $kind = $purpose === 'owner_drawing' ? 'owner_drawing'
                          : ($purpose === 'owner_salary' ? 'owner_salary' : 'expense');
                    $this->record($base + [
                        'kind'        => $kind,
                        'description' => 'Expense ' . $value . ' ' . strtoupper($ccy) . ($purpose ? ' (' . $purpose . ')' : ''),
                        'lines'       => [
                            ['account_code' => $debitCode, 'dr' => $value, 'cr' => 0, 'currency' => $ccy, 'branch_id' => $branch],
                            ['account_code' => '1000',     'dr' => 0, 'cr' => $value, 'currency' => $ccy, 'branch_id' => $branch],
                        ],
                    ], "expense bt#$r->id");
                }
            } else {
                $this->stats['unknown']++;
                $this->unknownReasons[] = "branches_transactions#$r->id type=$type plus_minus=$r->plus_minus purpose=$purpose";
            }

            if ($this->tickLimit()) return;
        }
    }

    /* ============================================================
     *  Suppliers
     * ============================================================ */
    private function processSupplierTransactions(): void
    {
        $this->info('-- suppliers_transactions');
        $rows = DB::table('suppliers_transactions')->orderBy('id')->get();
        foreach ($rows as $r) {
            // Note: legacy entries used auto_id as source_id, new entries use
            // id. The transaction_number check covers both.
            if ($this->alreadyJournaled('suppliers_transactions', (int) $r->id, $r->transaction_number)
                || $this->alreadyJournaled('suppliers_transactions', (int) $r->auto_id, $r->transaction_number)) {
                $this->stats['skipped_existing']++;
                continue;
            }
            $value = (float) $r->value;
            $ccy = $r->currency;
            $base = [
                'entry_date'         => $r->created_date,
                'source_table'       => 'suppliers_transactions',
                'source_id'          => (int) $r->id,
                'transaction_number' => $r->transaction_number,
                'branch_id'          => is_numeric($r->branch) ? (int) $r->branch : null,
            ];
            if ($r->plus_minus === 'plus') {
                // We paid the supplier — prepayment increases, cash decreases.
                $this->record($base + [
                    'kind'        => 'supplier_deposit',
                    'description' => 'Paid supplier ' . $value . ' ' . strtoupper($ccy),
                    'lines'       => [
                        ['account_code' => '1200', 'dr' => $value, 'cr' => 0, 'currency' => $ccy, 'counterparty_type' => 'supplier', 'counterparty_id' => (int) $r->supplier_id],
                        ['account_code' => '1000', 'dr' => 0, 'cr' => $value, 'currency' => $ccy, 'counterparty_type' => 'supplier', 'counterparty_id' => (int) $r->supplier_id],
                    ],
                ], "supplier_deposit st#$r->id");
            } else {
                // Supplier refund / reduction of prepayment.
                $this->record($base + [
                    'kind'        => 'supplier_refund',
                    'description' => 'Supplier refund ' . $value . ' ' . strtoupper($ccy),
                    'lines'       => [
                        ['account_code' => '1000', 'dr' => $value, 'cr' => 0, 'currency' => $ccy, 'counterparty_type' => 'supplier', 'counterparty_id' => (int) $r->supplier_id],
                        ['account_code' => '1200', 'dr' => 0, 'cr' => $value, 'currency' => $ccy, 'counterparty_type' => 'supplier', 'counterparty_id' => (int) $r->supplier_id],
                    ],
                ], "supplier_refund st#$r->id");
            }
            if ($this->tickLimit()) return;
        }
    }

    /* ============================================================
     *  Brokers
     * ============================================================ */
    private function processBrokerTransactions(): void
    {
        $this->info('-- customs_brokers_transactions');
        $rows = DB::table('customs_brokers_transactions')->orderBy('id')->get();
        foreach ($rows as $r) {
            if ($this->alreadyJournaled('customs_brokers_transactions', (int) $r->id, $r->transaction_number)
                || $this->alreadyJournaled('customs_brokers_transactions', (int) $r->auto_id, $r->transaction_number)) {
                $this->stats['skipped_existing']++;
                continue;
            }
            $value = (float) $r->value;
            $ccy = $r->currency;
            $base = [
                'entry_date'         => $r->created_date,
                'source_table'       => 'customs_brokers_transactions',
                'source_id'          => (int) $r->id,
                'transaction_number' => $r->transaction_number,
                'branch_id'          => is_numeric($r->branch) ? (int) $r->branch : null,
            ];
            if ($r->plus_minus === 'plus') {
                $this->record($base + [
                    'kind'        => 'broker_deposit',
                    'description' => 'Paid broker ' . $value . ' ' . strtoupper($ccy),
                    'lines'       => [
                        ['account_code' => '1300', 'dr' => $value, 'cr' => 0, 'currency' => $ccy, 'counterparty_type' => 'customs_broker', 'counterparty_id' => (int) $r->broker_id],
                        ['account_code' => '1000', 'dr' => 0, 'cr' => $value, 'currency' => $ccy, 'counterparty_type' => 'customs_broker', 'counterparty_id' => (int) $r->broker_id],
                    ],
                ], "broker_deposit bkt#$r->id");
            } else {
                $this->record($base + [
                    'kind'        => 'broker_refund',
                    'description' => 'Broker refund ' . $value . ' ' . strtoupper($ccy),
                    'lines'       => [
                        ['account_code' => '1000', 'dr' => $value, 'cr' => 0, 'currency' => $ccy, 'counterparty_type' => 'customs_broker', 'counterparty_id' => (int) $r->broker_id],
                        ['account_code' => '1300', 'dr' => 0, 'cr' => $value, 'currency' => $ccy, 'counterparty_type' => 'customs_broker', 'counterparty_id' => (int) $r->broker_id],
                    ],
                ], "broker_refund bkt#$r->id");
            }
            if ($this->tickLimit()) return;
        }
    }
}
