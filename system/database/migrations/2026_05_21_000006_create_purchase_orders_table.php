<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();

            // Customer ref — points at the existing legacy `clients` table
            // whose `id` is signed INT (not bigint unsigned), so a proper FK
            // constraint isn't type-compatible with Laravel's foreignId().
            // Kept as an indexed integer until clients.id is widened in a
            // future migration. See ALIGNMENT_PATCH.md §2.7-ish (legacy schema notes).
            $table->unsignedInteger('customer_id')->index();

            // الموقع والمسؤول
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();

            // العملات
            $table->string('purchase_currency', 3);
            $table->string('customer_currency', 3);

            // سعر الصرف المُجمّد
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates')->nullOnDelete();
            $table->decimal('frozen_exchange_rate', 18, 8)->nullable();
            $table->timestamp('exchange_rate_frozen_at')->nullable();

            // المبالغ المُقدّرة
            $table->decimal('estimated_purchase_amount', 14, 2)->nullable();
            $table->decimal('estimated_total_usd', 14, 2)->nullable();

            // المبالغ الفعلية
            $table->decimal('actual_purchase_amount', 14, 2)->nullable();
            $table->decimal('actual_total_usd', 14, 2)->nullable();

            // العمولة
            $table->enum('commission_type', ['NONE', 'PERCENTAGE', 'FIXED_AMOUNT'])->default('PERCENTAGE');
            $table->decimal('commission_value', 10, 4)->nullable();
            $table->decimal('commission_amount', 12, 2)->nullable();
            $table->text('commission_notes')->nullable();

            // المخصوم من العميل
            $table->decimal('customer_charged_amount', 14, 2)->nullable();
            $table->decimal('customer_charged_usd', 14, 2)->nullable();

            // الحالة
            $table->enum('status', [
                'PENDING_CONFIRMATION', 'CONFIRMED', 'PURCHASING', 'PURCHASED',
                'RECEIVED_WAREHOUSE', 'IN_SHIPMENT', 'DELIVERED',
                'CANCELLED', 'RETURNED', 'REFUNDED', 'ON_HOLD'
            ])->default('PENDING_CONFIRMATION');

            // ملاحظات
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('contact_source', 50)->nullable();

            // ربط مع الشحن
            $table->foreignId('shipment_id')->nullable();
            $table->foreignId('container_id')->nullable();
            $table->timestamp('added_to_shipment_at')->nullable();

            // التتبع
            $table->string('tracking_number')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_contact')->nullable();

            // التواريخ المهمة
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('purchasing_started_at')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // الإلغاء
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();

            // التتبع
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();

            // Idempotency
            $table->string('idempotency_key')->nullable()->unique();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['buyer_id', 'status']);
            $table->index(['warehouse_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
