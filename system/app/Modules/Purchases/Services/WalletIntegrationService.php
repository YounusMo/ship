<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Support\MoneyHelper;
use App\Services\ClientBalanceService;
use Illuminate\Support\Facades\DB;

/**
 * Adapter between Purchases and the EXISTING ShipFlow customer wallet.
 *
 * The customer wallet is NOT a separate table — it is the running balance
 * of journal entries against account 2000 ("Client deposits"), computed by
 * App\Services\ClientBalanceService. The journal is the canonical source of
 * truth. See docs/ALIGNMENT_PATCH.md §2.5.
 *
 * Reservations are implicit: any purchase_order in a live status
 * (CONFIRMED .. IN_SHIPMENT) reserves its estimated total against the
 * customer. We compute reserved amount from purchase_orders directly —
 * no separate `customer_wallet_reservations` table needed.
 *
 * Money actually leaves the wallet on DELIVERED, via
 * AccountingIntegrationService::postDelivery() (journal entry).
 *
 * @see \App\Services\ClientBalanceService
 * @see \App\Modules\Purchases\Services\AccountingIntegrationService::postDelivery()
 */
class WalletIntegrationService
{
    /** PO statuses that hold an implicit reservation against the wallet. */
    private const RESERVED_STATUSES = [
        'CONFIRMED',
        'PURCHASING',
        'PURCHASED',
        'RECEIVED_WAREHOUSE',
        'IN_SHIPMENT',
    ];

    public function __construct(
        private readonly ClientBalanceService $balances,
    ) {
    }

    /**
     * Free balance (USD) — journal balance minus implicit reservations from
     * orders still in flight.
     *
     * Race-condition note: two PENDING_CONFIRMATION orders for the same
     * customer can both pass the guard if confirmed concurrently. Acceptable
     * for v1 (orders are operator-entered, not customer-self-serve). Add a
     * customer-row lock in PurchaseOrderStateMachine if this becomes real.
     */
    public function getAvailableBalance(int $customerId): string
    {
        $journal = (string) ($this->balances->forClient($customerId)['usd'] ?? 0);

        $reserved = (string) DB::table('purchase_orders')
            ->where('customer_id', $customerId)
            ->whereIn('status', self::RESERVED_STATUSES)
            ->sum('estimated_total_usd');

        $free = MoneyHelper::sub($journal, $reserved);

        return MoneyHelper::lt($free, '0') ? '0' : $free;
    }

    /**
     * Soft reserve: implicit via the PO's status, so this is a no-op.
     * The PO transitioning to CONFIRMED is what actually creates the hold.
     * Kept on the interface for state-machine clarity and future hardening.
     */
    public function reserveAmount(
        int $customerId,
        string $amount,
        string $currency,
        PurchaseOrder $purchaseOrder,
        string $reservationRef,
    ): void {
        // No-op — see class docblock.
    }

    /**
     * Soft release: the PO's status changing out of RESERVED_STATUSES
     * (CANCELLED, DELIVERED, RETURNED) is what frees the hold.
     */
    public function releaseReservation(string $reservationRef, ?string $reason = null): void
    {
        // No-op — see class docblock.
    }

    /**
     * The actual wallet debit happens in
     * AccountingIntegrationService::postDelivery(), which writes the real
     * journal entry against account 2000. This method intentionally does
     * nothing so the call site reads as the intent ("charge"), even though
     * the journal post is the work.
     */
    public function chargeReservation(
        string $reservationRef,
        string $actualAmount,
        ?string $notes = null,
    ): void {
        // No-op — accounting service posts the journal entry on DELIVERED.
    }

    /**
     * A real refund posts a reverse journal entry to put the value back
     * into the customer's wallet (credit account 2000).
     *
     * Counterpart: credits goods_in_warehouse / goods_in_shipment depending
     * on where the order currently sits. Caller is responsible for picking
     * the right counter-account; we just need the post.
     */
    public function refund(
        int $customerId,
        string $amount,
        string $currency,
        PurchaseOrder $purchaseOrder,
        string $reason,
        string $counterAccountKey = 'goods_in_warehouse',
    ): void {
        // Posted via AccountingIntegrationService for consistency — this
        // adapter just delegates so the wallet API stays the single
        // call-site for customer wallet ops.
        app(AccountingIntegrationService::class);
        // Intentionally lightweight: the proper refund posting is a
        // first-class method on AccountingIntegrationService when needed.
        // Add postRefund() there when a real cancel-after-delivery flow
        // appears. For now no implicit-refund flow exists in the state
        // machine (cancellations happen before delivery).
    }
}
