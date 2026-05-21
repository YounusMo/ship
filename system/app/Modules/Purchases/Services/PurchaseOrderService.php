<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Purchases\Enums\CommissionType;
use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Models\Buyer;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\StateMachine\PurchaseOrderStateMachine;
use App\Modules\Purchases\Support\MoneyHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * الخدمة الرئيسية لإدارة طلبات الشراء
 *
 * @see CLAUDE.md Section 3 - State Machine
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly PurchaseOrderStateMachine $stateMachine,
        private readonly ExchangeRateService $exchangeRateService,
        private readonly CommissionService $commissionService,
        private readonly AuditLogService $audit,
    ) {
    }

    /**
     * إنشاء طلب جديد
     *
     * @param  array{
     *     customer_id: int,
     *     warehouse_id: int,
     *     purchase_currency: string,
     *     customer_currency: string,
     *     commission_type: CommissionType,
     *     commission_value?: string|null,
     *     commission_notes?: string|null,
     *     customer_notes?: string|null,
     *     contact_source?: string|null,
     *     items: array<int, array<string, mixed>>,
     * }  $input
     */
    public function createOrder(array $input, ?string $idempotencyKey = null): PurchaseOrder
    {
        // Idempotency check
        if ($idempotencyKey !== null) {
            $existing = PurchaseOrder::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        // التحقق من العمولة
        $this->commissionService->validate(
            $input['commission_type'],
            $input['commission_value'] ?? null,
            $input['commission_notes'] ?? null,
        );

        return DB::transaction(function () use ($input, $idempotencyKey) {
            // احسب المبالغ المُقدّرة من البنود
            $estimatedPurchaseAmount = '0.00';
            foreach ($input['items'] as $item) {
                $itemAmount = MoneyHelper::mul(
                    (string) $item['unit_price'],
                    (string) $item['quantity'],
                );
                $estimatedPurchaseAmount = MoneyHelper::add($estimatedPurchaseAmount, $itemAmount);
            }

            // حوّل لـ USD
            $estimatedTotalUsd = $this->convertToUsd(
                $estimatedPurchaseAmount,
                $input['purchase_currency'],
            );

            // حساب العمولة
            $commissionResult = $this->commissionService->calculate(
                $estimatedTotalUsd,
                $input['commission_type'],
                $input['commission_value'] ?? null,
                $input['commission_notes'] ?? null,
            );

            // إنشاء الطلب
            $order = PurchaseOrder::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $input['customer_id'],
                'warehouse_id' => $input['warehouse_id'],
                'purchase_currency' => $input['purchase_currency'],
                'customer_currency' => $input['customer_currency'],
                'estimated_purchase_amount' => $estimatedPurchaseAmount,
                'estimated_total_usd' => $commissionResult['total_amount'],
                'commission_type' => $input['commission_type'],
                'commission_value' => $input['commission_value'] ?? null,
                'commission_amount' => $commissionResult['commission_amount'],
                'commission_notes' => $input['commission_notes'] ?? null,
                'status' => PurchaseOrderStatus::PENDING_CONFIRMATION,
                'customer_notes' => $input['customer_notes'] ?? null,
                'contact_source' => $input['contact_source'] ?? null,
                'requested_at' => now(),
                'created_by_id' => Auth::id() ?? 1,
                'idempotency_key' => $idempotencyKey,
            ]);

            // إنشاء البنود
            foreach ($input['items'] as $itemData) {
                $estimatedAmount = MoneyHelper::mul(
                    (string) $itemData['unit_price'],
                    (string) $itemData['quantity'],
                );

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_name' => $itemData['product_name'],
                    'product_name_ar' => $itemData['product_name_ar'] ?? null,
                    'description' => $itemData['description'] ?? null,
                    'product_url' => $itemData['product_url'] ?? null,
                    'image_url' => $itemData['image_url'] ?? null,
                    'supplier_name' => $itemData['supplier_name'] ?? null,
                    'supplier_url' => $itemData['supplier_url'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'estimated_amount' => $estimatedAmount,
                    'currency' => $input['purchase_currency'],
                    'color' => $itemData['color'] ?? null,
                    'size' => $itemData['size'] ?? null,
                    'variant' => $itemData['variant'] ?? null,
                    'weight_kg' => $itemData['weight_kg'] ?? null,
                ]);
            }

            $this->audit->log(
                entityType: 'PurchaseOrder',
                entityId: (string) $order->id,
                action: 'CREATE',
                newValues: $order->toArray(),
            );

            return $order->fresh(['items']);
        });
    }

    /**
     * تعيين مسؤول مشتريات
     */
    public function assignBuyer(PurchaseOrder $order, Buyer $buyer): PurchaseOrder
    {
        if ($order->warehouse_id !== $buyer->primary_warehouse_id) {
            throw new \App\Modules\Purchases\Exceptions\PurchaseException(
                message: 'Buyer warehouse mismatch',
                messageAr: 'مسؤول المشتريات يعمل في مستودع آخر',
            );
        }

        $oldBuyerId = $order->buyer_id;
        $order->update(['buyer_id' => $buyer->id]);

        $this->audit->log(
            entityType: 'PurchaseOrder',
            entityId: (string) $order->id,
            action: 'ASSIGN_BUYER',
            oldValues: ['buyer_id' => $oldBuyerId],
            newValues: ['buyer_id' => $buyer->id],
        );

        return $order->fresh();
    }

    /**
     * تأكيد الطلب (الانتقال PENDING_CONFIRMATION → CONFIRMED)
     */
    public function confirm(PurchaseOrder $order): PurchaseOrder
    {
        return $this->stateMachine->transition($order, PurchaseOrderStatus::CONFIRMED);
    }

    /**
     * بدء الشراء
     */
    public function startPurchasing(PurchaseOrder $order): PurchaseOrder
    {
        return $this->stateMachine->transition($order, PurchaseOrderStatus::PURCHASING);
    }

    /**
     * تأكيد إتمام الشراء (مع رفع الفاتورة)
     *
     * @param  array{
     *     actual_amount: string,
     *     exchange_rate: string,
     *     invoice_image_url: string,
     *     supplier_name?: string|null,
     *     tracking_number?: string|null,
     *     notes?: string|null,
     * }  $context
     */
    public function markPurchased(PurchaseOrder $order, array $context): PurchaseOrder
    {
        return $this->stateMachine->transition($order, PurchaseOrderStatus::PURCHASED, $context);
    }

    /**
     * استلام في المستودع
     */
    public function markReceived(PurchaseOrder $order, ?string $notes = null): PurchaseOrder
    {
        return $this->stateMachine->transition($order, PurchaseOrderStatus::RECEIVED_WAREHOUSE, [
            'notes' => $notes,
        ]);
    }

    /**
     * إضافة لرحلة شحن
     */
    public function addToShipment(PurchaseOrder $order, int $shipmentId, ?int $containerId = null): PurchaseOrder
    {
        return $this->stateMachine->transition($order, PurchaseOrderStatus::IN_SHIPMENT, [
            'shipment_id' => $shipmentId,
            'container_id' => $containerId,
        ]);
    }

    /**
     * التسليم النهائي للعميل
     */
    public function markDelivered(PurchaseOrder $order): PurchaseOrder
    {
        return $this->stateMachine->transition($order, PurchaseOrderStatus::DELIVERED);
    }

    /**
     * إلغاء الطلب
     */
    public function cancel(PurchaseOrder $order, string $reason): PurchaseOrder
    {
        return $this->stateMachine->transition($order, PurchaseOrderStatus::CANCELLED, [
            'reason' => $reason,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function convertToUsd(string $amount, string $currency): string
    {
        if ($currency === 'USD') {
            return $amount;
        }

        $rate = $this->exchangeRateService->getCurrentRate('USD', $currency);
        if ($rate === null) {
            throw \App\Modules\Purchases\Exceptions\ExchangeRateException::notAvailable('USD', $currency);
        }

        return MoneyHelper::div($amount, $rate);
    }

    private function generateOrderNumber(): string
    {
        $year = now()->year;
        $prefix = "PO-{$year}-";

        $lastOrder = PurchaseOrder::query()
            ->where('order_number', 'like', "{$prefix}%")
            ->orderByDesc('order_number')
            ->first();

        $sequence = 1;
        if ($lastOrder !== null) {
            $lastSeq = (int) substr($lastOrder->order_number, strlen($prefix));
            $sequence = $lastSeq + 1;
        }

        return sprintf('%s%05d', $prefix, $sequence);
    }
}
