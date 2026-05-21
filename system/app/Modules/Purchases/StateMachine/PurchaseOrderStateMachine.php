<?php

declare(strict_types=1);

namespace App\Modules\Purchases\StateMachine;

use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Events\PurchaseOrderStatusChanged;
use App\Modules\Purchases\Exceptions\InvalidTransitionException;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderStatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purchase Order State Machine
 *
 * @see CLAUDE.md Section 3 - State Machine
 *
 * كل انتقال:
 * 1. تحقق من السماحية
 * 2. تنفيذ guard (شروط الانتقال)
 * 3. تنفيذ effect (التأثيرات الجانبية)
 * 4. تحديث الحالة + التاريخ
 * 5. تسجيل في status_history
 * 6. إطلاق Event
 */
class PurchaseOrderStateMachine
{
    /**
     * @var array<string, array<int, Transition>>
     */
    private array $transitions;

    public function __construct()
    {
        $this->transitions = $this->buildTransitionsMap();
    }

    /**
     * تنفيذ انتقال
     *
     * @param  array<string, mixed>  $context  بيانات إضافية للانتقال (reason, shipment_id, etc.)
     */
    public function transition(
        PurchaseOrder $order,
        PurchaseOrderStatus $toStatus,
        array $context = [],
    ): PurchaseOrder {
        $fromStatus = $order->status;

        // ابحث عن الانتقال
        $transition = $this->findTransition($fromStatus, $toStatus);
        if ($transition === null) {
            throw InvalidTransitionException::notAllowed($fromStatus, $toStatus, $order->id);
        }

        return DB::transaction(function () use ($order, $fromStatus, $toStatus, $transition, $context) {
            // 🔒 lock الـ row لمنع race conditions
            $order = PurchaseOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new \RuntimeException("Order not found");
            }

            // re-check بعد lock (الحالة ممكن تكون اتغيرت)
            if ($order->status !== $fromStatus) {
                throw InvalidTransitionException::notAllowed($order->status, $toStatus, $order->id);
            }

            // Guard: تحقق من الشروط
            $transition->checkGuard($order, $context);

            // Effect: نفّذ التأثيرات الجانبية
            $transition->executeEffect($order, $context);

            // حدّث الحالة + التاريخ
            $order->status = $toStatus;
            $this->updateTimestamp($order, $toStatus);
            $order->save();

            // سجّل في الـ history
            PurchaseOrderStatusHistory::create([
                'purchase_order_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $context['reason'] ?? null,
                'notes' => $context['notes'] ?? null,
                'performed_by_id' => Auth::id() ?? $context['performed_by_id'] ?? null,
                'ip_address' => request()?->ip(),
                'changed_at' => now(),
            ]);

            // Log
            Log::info('Purchase order status changed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'from' => $fromStatus->value,
                'to' => $toStatus->value,
                'context' => $context,
            ]);

            // أطلق الـ event بعد الـ transaction (في afterCommit)
            DB::afterCommit(function () use ($order, $fromStatus, $toStatus) {
                event(new PurchaseOrderStatusChanged($order, $fromStatus, $toStatus));
            });

            return $order->fresh();
        });
    }

    /**
     * هل الانتقال مسموح؟
     */
    public function canTransition(
        PurchaseOrderStatus $from,
        PurchaseOrderStatus $to,
    ): bool {
        return $this->findTransition($from, $to) !== null;
    }

    /**
     * احصل على الحالات المتاحة للانتقال من حالة معينة
     *
     * @return array<int, PurchaseOrderStatus>
     */
    public function availableTransitions(PurchaseOrderStatus $from): array
    {
        $key = $from->value;
        $transitions = $this->transitions[$key] ?? [];
        return array_map(fn(Transition $t) => $t->to, $transitions);
    }

    private function findTransition(
        PurchaseOrderStatus $from,
        PurchaseOrderStatus $to,
    ): ?Transition {
        $key = $from->value;
        foreach ($this->transitions[$key] ?? [] as $transition) {
            if ($transition->to === $to) {
                return $transition;
            }
        }
        return null;
    }

    private function updateTimestamp(PurchaseOrder $order, PurchaseOrderStatus $status): void
    {
        $columnMap = [
            PurchaseOrderStatus::CONFIRMED->value => 'confirmed_at',
            PurchaseOrderStatus::PURCHASING->value => 'purchasing_started_at',
            PurchaseOrderStatus::PURCHASED->value => 'purchased_at',
            PurchaseOrderStatus::RECEIVED_WAREHOUSE->value => 'received_at',
            PurchaseOrderStatus::IN_SHIPMENT->value => 'shipped_at',
            PurchaseOrderStatus::DELIVERED->value => 'delivered_at',
            PurchaseOrderStatus::CANCELLED->value => 'cancelled_at',
        ];

        if (isset($columnMap[$status->value])) {
            $order->{$columnMap[$status->value]} = now();
        }
    }

    /**
     * بناء خريطة الانتقالات
     *
     * @return array<string, array<int, Transition>>
     */
    private function buildTransitionsMap(): array
    {
        $transitions = [
            // ─── من PENDING_CONFIRMATION ──────────────────────────
            new Transition(
                from: PurchaseOrderStatus::PENDING_CONFIRMATION,
                to: PurchaseOrderStatus::CONFIRMED,
                guardClass: Guards\ConfirmGuard::class,
                effectClass: Effects\ConfirmEffect::class,
            ),
            new Transition(
                from: PurchaseOrderStatus::PENDING_CONFIRMATION,
                to: PurchaseOrderStatus::CANCELLED,
                effectClass: Effects\CancelEffect::class,
            ),

            // ─── من CONFIRMED ──────────────────────────────────────
            new Transition(
                from: PurchaseOrderStatus::CONFIRMED,
                to: PurchaseOrderStatus::PURCHASING,
                guardClass: Guards\StartPurchasingGuard::class,
                effectClass: Effects\StartPurchasingEffect::class,
            ),
            new Transition(
                from: PurchaseOrderStatus::CONFIRMED,
                to: PurchaseOrderStatus::CANCELLED,
                effectClass: Effects\CancelAfterConfirmEffect::class,
            ),

            // ─── من PURCHASING ─────────────────────────────────────
            new Transition(
                from: PurchaseOrderStatus::PURCHASING,
                to: PurchaseOrderStatus::PURCHASED,
                guardClass: Guards\MarkPurchasedGuard::class,
                effectClass: Effects\MarkPurchasedEffect::class,
            ),
            new Transition(
                from: PurchaseOrderStatus::PURCHASING,
                to: PurchaseOrderStatus::CANCELLED,
                effectClass: Effects\CancelAfterConfirmEffect::class,
            ),

            // ─── من PURCHASED ──────────────────────────────────────
            new Transition(
                from: PurchaseOrderStatus::PURCHASED,
                to: PurchaseOrderStatus::RECEIVED_WAREHOUSE,
                guardClass: Guards\MarkReceivedGuard::class,
                effectClass: Effects\MarkReceivedEffect::class,
            ),
            new Transition(
                from: PurchaseOrderStatus::PURCHASED,
                to: PurchaseOrderStatus::CANCELLED,
                guardClass: Guards\CancelAfterPurchaseGuard::class,
                effectClass: Effects\CancelAfterPurchaseEffect::class,
            ),

            // ─── من RECEIVED_WAREHOUSE ─────────────────────────────
            new Transition(
                from: PurchaseOrderStatus::RECEIVED_WAREHOUSE,
                to: PurchaseOrderStatus::IN_SHIPMENT,
                guardClass: Guards\AddToShipmentGuard::class,
                effectClass: Effects\AddToShipmentEffect::class,
            ),
            new Transition(
                from: PurchaseOrderStatus::RECEIVED_WAREHOUSE,
                to: PurchaseOrderStatus::ON_HOLD,
                effectClass: Effects\HoldEffect::class,
            ),

            // ─── من IN_SHIPMENT ────────────────────────────────────
            new Transition(
                from: PurchaseOrderStatus::IN_SHIPMENT,
                to: PurchaseOrderStatus::DELIVERED,
                guardClass: Guards\MarkDeliveredGuard::class,
                effectClass: Effects\MarkDeliveredEffect::class,
            ),

            // ─── من DELIVERED ──────────────────────────────────────
            new Transition(
                from: PurchaseOrderStatus::DELIVERED,
                to: PurchaseOrderStatus::RETURNED,
                guardClass: Guards\ReturnGuard::class,
                effectClass: Effects\ReturnEffect::class,
            ),

            // ─── من ON_HOLD ────────────────────────────────────────
            // (يمكن العودة لأي حالة سابقة، يُعالج في handler منفصل)
        ];

        $map = [];
        foreach ($transitions as $t) {
            $map[$t->from->value][] = $t;
        }
        return $map;
    }
}
