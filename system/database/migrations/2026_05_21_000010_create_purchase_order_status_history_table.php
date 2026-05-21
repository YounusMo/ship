<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل تغيير حالات الطلب - Append-Only
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('performed_by_id')->constrained('users')->restrictOnDelete();
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('changed_at')->useCurrent();

            $table->index(['purchase_order_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_status_history');
    }
};
