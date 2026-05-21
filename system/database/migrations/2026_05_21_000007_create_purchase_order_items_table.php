<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

            // معلومات المنتج
            $table->string('product_name');
            $table->string('product_name_ar')->nullable();
            $table->text('description')->nullable();
            $table->string('product_url')->nullable();
            $table->string('image_url')->nullable();

            // المورد
            $table->string('supplier_name')->nullable();
            $table->string('supplier_url')->nullable();

            // الكمية والسعر
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 14, 2);

            // المبالغ
            $table->decimal('estimated_amount', 14, 2);
            $table->decimal('actual_amount', 14, 2)->nullable();
            $table->string('currency', 3);

            // المواصفات
            $table->string('color', 50)->nullable();
            $table->string('size', 50)->nullable();
            $table->string('variant')->nullable();
            $table->json('specifications')->nullable();

            // الوزن والأبعاد
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->decimal('volume_m3', 10, 6)->nullable();

            // الحالة
            $table->enum('item_status', [
                'PENDING', 'PURCHASED', 'RECEIVED', 'PARTIALLY_RECEIVED',
                'DAMAGED', 'WRONG_ITEM', 'RETURNED'
            ])->default('PENDING');
            $table->unsignedInteger('received_qty')->default(0);
            $table->timestamp('received_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'item_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
