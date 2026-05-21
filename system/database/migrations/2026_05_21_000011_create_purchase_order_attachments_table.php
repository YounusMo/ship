<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_order_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

            $table->enum('type', [
                'PURCHASE_INVOICE', 'PRODUCT_IMAGE', 'RECEIVED_PHOTO', 'PACKING_PHOTO',
                'TRACKING_SCREENSHOT', 'EXCHANGE_RATE_PROOF', 'CUSTOMER_REQUEST', 'OTHER'
            ]);
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_url')->nullable();
            $table->unsignedInteger('file_size');
            $table->string('mime_type', 100);

            $table->text('description')->nullable();

            $table->foreignId('uploaded_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at')->useCurrent();

            $table->index(['purchase_order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_attachments');
    }
};
