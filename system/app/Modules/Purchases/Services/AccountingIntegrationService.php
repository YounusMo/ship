<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Purchases\Models\Buyer;
use App\Modules\Purchases\Models\BuyerTransaction;
use App\Modules\Purchases\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Posts double-entry journal entries into the EXISTING ShipFlow accounting
 * tables — journal_entries + journal_lines — using the convention defined
 * by App\Http\Controllers\journalController.
 *
 * Account codes are NEVER hardcoded here. They are pulled from
 * config('purchases.accounts.*') so the wiring can be reviewed in one
 * place. See docs/ALIGNMENT_PATCH.md §2.4 for the mapping rationale.
 *
 * All purchases entries are USD. Per-currency balance assertion is kept
 * generic for future-proofing.
 */
class AccountingIntegrationService
{
    /** Per-request cache of chart_of_accounts rows keyed by code. */
    private array $accountCache = [];

    public function postInitialDeposit(
        Buyer $buyer,
        string $amount,
        BuyerTransaction $transaction,
    ): int {
        return $this->record(
            kind: 'purchases.buyer_deposit',
            description: "Initial float deposit for buyer {$buyer->code}",
            counterpartyType: 'buyer',
            counterpartyId: $buyer->id,
            lines: [
                ['account_code' => $this->code('buyer_float'), 'dr' => $amount, 'cr' => '0',     'description' => "Float deposit {$buyer->code}"],
                ['account_code' => $this->code('cash_bank'),   'dr' => '0',     'cr' => $amount, 'description' => 'Cash out'],
            ],
        );
    }

    public function postTopUp(
        Buyer $buyer,
        string $amount,
        BuyerTransaction $transaction,
    ): int {
        return $this->record(
            kind: 'purchases.buyer_topup',
            description: "Top-up float for buyer {$buyer->code}",
            counterpartyType: 'buyer',
            counterpartyId: $buyer->id,
            lines: [
                ['account_code' => $this->code('buyer_float'), 'dr' => $amount, 'cr' => '0'],
                ['account_code' => $this->code('cash_bank'),   'dr' => '0',     'cr' => $amount],
            ],
        );
    }

    public function postPurchaseExecution(
        Buyer $buyer,
        PurchaseOrder $order,
        string $usdAmount,
        BuyerTransaction $transaction,
    ): int {
        return $this->record(
            kind: 'purchases.execution',
            description: "Purchase executed for order {$order->order_number}",
            sourceTable: 'purchase_orders',
            sourceId: $order->id,
            counterpartyType: 'buyer',
            counterpartyId: $buyer->id,
            lines: [
                ['account_code' => $this->code('purchases_in_transit'), 'dr' => $usdAmount, 'cr' => '0',        'description' => "Order {$order->order_number}"],
                ['account_code' => $this->code('buyer_float'),          'dr' => '0',        'cr' => $usdAmount, 'description' => "Buyer {$buyer->code} float"],
            ],
        );
    }

    public function postPurchaseRefund(
        Buyer $buyer,
        PurchaseOrder $order,
        string $usdAmount,
        BuyerTransaction $transaction,
    ): int {
        return $this->record(
            kind: 'purchases.refund',
            description: "Purchase refund for order {$order->order_number}",
            sourceTable: 'purchase_orders',
            sourceId: $order->id,
            counterpartyType: 'buyer',
            counterpartyId: $buyer->id,
            lines: [
                ['account_code' => $this->code('buyer_float'),          'dr' => $usdAmount, 'cr' => '0'],
                ['account_code' => $this->code('purchases_in_transit'), 'dr' => '0',        'cr' => $usdAmount],
            ],
        );
    }

    public function postGoodsReceived(PurchaseOrder $order, string $usdAmount): int
    {
        return $this->record(
            kind: 'purchases.received_warehouse',
            description: "Goods received in warehouse for order {$order->order_number}",
            sourceTable: 'purchase_orders',
            sourceId: $order->id,
            lines: [
                ['account_code' => $this->code('goods_in_warehouse'),   'dr' => $usdAmount, 'cr' => '0'],
                ['account_code' => $this->code('purchases_in_transit'), 'dr' => '0',        'cr' => $usdAmount],
            ],
        );
    }

    public function postAddedToShipment(PurchaseOrder $order, string $usdAmount): int
    {
        return $this->record(
            kind: 'purchases.added_to_shipment',
            description: "Order {$order->order_number} added to shipment",
            sourceTable: 'purchase_orders',
            sourceId: $order->id,
            lines: [
                ['account_code' => $this->code('goods_in_shipment'),  'dr' => $usdAmount, 'cr' => '0'],
                ['account_code' => $this->code('goods_in_warehouse'), 'dr' => '0',        'cr' => $usdAmount],
            ],
        );
    }

    /**
     * Final delivery posting. Debits customer wallet liability (we now owe
     * less, customer has been served). Credits goods-in-shipment for the
     * goods cost. Optionally credits commission revenue.
     *
     * Customer wallet and customer liability are the SAME account in the
     * existing chart_of_accounts ("Client deposits", code 2000) — see
     * ALIGNMENT_PATCH.md §2.4.
     */
    public function postDelivery(
        PurchaseOrder $order,
        string $cogsAmount,
        ?string $commissionAmount = null,
    ): int {
        $lines = [];

        $totalDebit = $cogsAmount;
        if ($commissionAmount !== null && bccomp($commissionAmount, '0', 2) > 0) {
            $totalDebit = bcadd($cogsAmount, $commissionAmount, 2);
        }

        $lines[] = [
            'account_code'      => $this->code('customer_liability'),
            'dr'                => $totalDebit,
            'cr'                => '0',
            'description'       => "Delivered order {$order->order_number}",
            'counterparty_type' => 'client',
            'counterparty_id'   => $order->customer_id ?? null,
        ];

        $lines[] = [
            'account_code' => $this->code('goods_in_shipment'),
            'dr'           => '0',
            'cr'           => $cogsAmount,
            'description'  => "COGS — order {$order->order_number}",
        ];

        if ($commissionAmount !== null && bccomp($commissionAmount, '0', 2) > 0) {
            $lines[] = [
                'account_code' => $this->code('commission_revenue'),
                'dr'           => '0',
                'cr'           => $commissionAmount,
                'description'  => "Commission — order {$order->order_number}",
            ];
        }

        return $this->record(
            kind: 'purchases.delivered',
            description: "Order {$order->order_number} delivered",
            sourceTable: 'purchase_orders',
            sourceId: $order->id,
            lines: $lines,
        );
    }

    private function code(string $key): string
    {
        $code = config("purchases.accounts.$key");
        if (! $code) {
            throw new RuntimeException("purchases.accounts.{$key} is not configured");
        }
        return (string) $code;
    }

    private function resolveAccount(string $code): object
    {
        if (isset($this->accountCache[$code])) {
            return $this->accountCache[$code];
        }
        $row = DB::table('chart_of_accounts')->where('code', $code)->first();
        if (! $row) {
            throw new RuntimeException(
                "chart_of_accounts row missing for code {$code} — "
                . 'did you run PurchasesChartOfAccountsSeeder?'
            );
        }
        return $this->accountCache[$code] = $row;
    }

    /**
     * Records a journal entry the same shape the existing journalController
     * produces. Returns the new journal_entries.id.
     *
     * @param  array<int, array{account_code: string, dr?: string|int|float, cr?: string|int|float, currency?: string, description?: string, counterparty_type?: string|null, counterparty_id?: int|null, branch_id?: int|null}>  $lines
     */
    private function record(
        string $kind,
        string $description,
        array $lines,
        ?string $sourceTable = null,
        ?int $sourceId = null,
        ?string $counterpartyType = null,
        ?int $counterpartyId = null,
    ): int {
        $totals = [];
        foreach ($lines as $i => $line) {
            $ccy = $line['currency'] ?? 'USD';
            $totals[$ccy] = $totals[$ccy] ?? ['dr' => '0', 'cr' => '0'];
            $totals[$ccy]['dr'] = bcadd($totals[$ccy]['dr'], (string) ($line['dr'] ?? '0'), 4);
            $totals[$ccy]['cr'] = bcadd($totals[$ccy]['cr'], (string) ($line['cr'] ?? '0'), 4);
        }
        foreach ($totals as $ccy => $t) {
            if (bccomp($t['dr'], $t['cr'], 4) !== 0) {
                throw new RuntimeException(
                    "Unbalanced journal entry for {$ccy}: dr={$t['dr']} cr={$t['cr']}"
                );
            }
        }

        $userId = auth()->id();
        $userName = auth()->user()?->name;
        $entryId = 0;

        DB::transaction(function () use (
            $kind, $description, $lines, $sourceTable, $sourceId,
            $counterpartyType, $counterpartyId, $userId, $userName, &$entryId,
        ) {
            $entryId = DB::table('journal_entries')->insertGetId([
                'entry_date'          => date('Y-m-d'),
                'posted_at'           => date('Y-m-d H:i:s'),
                'posted_by_user_id'   => $userId,
                'posted_by_user_name' => $userName,
                'kind'                => $kind,
                'description'         => mb_substr($description, 0, 500),
                'source_table'        => $sourceTable,
                'source_id'           => $sourceId,
                'transaction_number'  => null,
                'branch_id'           => null,
                'status'              => 'open',
                'created_at'          => date('Y-m-d H:i:s'),
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);

            $lineNo = 0;
            foreach ($lines as $line) {
                $lineNo++;
                $acct = $this->resolveAccount((string) $line['account_code']);
                DB::table('journal_lines')->insert([
                    'entry_id'          => $entryId,
                    'line_no'           => $lineNo,
                    'account_id'        => $acct->id,
                    'account_code'      => $acct->code,
                    'account_name'      => $acct->name,
                    'dr'                => (float) ($line['dr'] ?? 0),
                    'cr'                => (float) ($line['cr'] ?? 0),
                    'currency'          => $line['currency'] ?? 'USD',
                    'description'       => mb_substr((string) ($line['description'] ?? ''), 0, 500),
                    'counterparty_type' => $line['counterparty_type'] ?? $counterpartyType,
                    'counterparty_id'   => $line['counterparty_id'] ?? $counterpartyId,
                    'branch_id'         => $line['branch_id'] ?? null,
                    'created_at'        => date('Y-m-d H:i:s'),
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);
            }
        });

        return $entryId;
    }
}
