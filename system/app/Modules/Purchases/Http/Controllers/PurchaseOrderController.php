<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchases\Http\Requests\CreatePurchaseOrderRequest;
use App\Modules\Purchases\Http\Requests\MarkPurchasedRequest;
use App\Modules\Purchases\Http\Resources\PurchaseOrderResource;
use App\Modules\Purchases\Models\Buyer;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
    ) {
    }

    /**
     * GET /api/purchases
     */
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::query()
            ->with(['customer', 'warehouse', 'buyer', 'items']);

        // فلترة حسب الصلاحيات
        $user = $request->user();
        if ($user && method_exists($user, 'buyer') && $user->buyer) {
            // المسؤول يشوف فقط طلباته
            $query->where('buyer_id', $user->buyer->id);
        }

        // فلاتر
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $orders = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => PurchaseOrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * POST /api/purchases
     */
    public function store(CreatePurchaseOrderRequest $request): JsonResponse
    {
        $order = $this->service->createOrder(
            input: $request->validated(),
            idempotencyKey: $request->header('Idempotency-Key'),
        );

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($order),
        ], 201);
    }

    /**
     * GET /api/purchases/{order}
     */
    public function show(PurchaseOrder $order): JsonResponse
    {
        $order->load(['customer', 'warehouse', 'buyer', 'items', 'attachments', 'statusHistory']);

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($order),
        ]);
    }

    /**
     * POST /api/purchases/{order}/confirm
     */
    public function confirm(PurchaseOrder $order): JsonResponse
    {
        $order = $this->service->confirm($order);

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($order),
        ]);
    }

    /**
     * POST /api/purchases/{order}/assign-buyer
     */
    public function assignBuyer(Request $request, PurchaseOrder $order): JsonResponse
    {
        $request->validate(['buyer_id' => 'required|exists:buyers,id']);

        $buyer = Buyer::findOrFail($request->input('buyer_id'));
        $order = $this->service->assignBuyer($order, $buyer);

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($order),
        ]);
    }

    /**
     * POST /api/purchases/{order}/start-purchasing
     */
    public function startPurchasing(PurchaseOrder $order): JsonResponse
    {
        $order = $this->service->startPurchasing($order);

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($order),
        ]);
    }

    /**
     * POST /api/purchases/{order}/mark-purchased
     */
    public function markPurchased(MarkPurchasedRequest $request, PurchaseOrder $order): JsonResponse
    {
        $order = $this->service->markPurchased($order, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($order),
        ]);
    }

    /**
     * POST /api/purchases/{order}/mark-received
     */
    public function markReceived(Request $request, PurchaseOrder $order): JsonResponse
    {
        $order = $this->service->markReceived($order, $request->input('notes'));

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($order),
        ]);
    }

    /**
     * POST /api/purchases/{order}/add-to-shipment
     */
    public function addToShipment(Request $request, PurchaseOrder $order): JsonResponse
    {
        $request->validate([
            'shipment_id' => 'required|integer|exists:shipments,id',
            'container_id' => 'nullable|integer',
        ]);

        $order = $this->service->addToShipment(
            $order,
            $request->integer('shipment_id'),
            $request->input('container_id'),
        );

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($order),
        ]);
    }

    /**
     * POST /api/purchases/{order}/mark-delivered
     */
    public function markDelivered(PurchaseOrder $order): JsonResponse
    {
        $order = $this->service->markDelivered($order);

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($order),
        ]);
    }

    /**
     * POST /api/purchases/{order}/cancel
     */
    public function cancel(Request $request, PurchaseOrder $order): JsonResponse
    {
        $request->validate(['reason' => 'required|string|min:10|max:1000']);

        $order = $this->service->cancel($order, $request->input('reason'));

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($order),
        ]);
    }
}
