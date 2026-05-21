<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول معاملات العُهدة - Append-Only
 * لا يُحذف ولا يُعدّل منه أبداً
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('buyer_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('buyer_account_id')->constrained('buyer_accounts')->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('buyers')->restrictOnDelete();

            $table->enum('type', [
                'INITIAL_DEPOSIT', 'TOP_UP', 'PURCHASE', 'PURCHASE_REFUND',
                'RECONCILIATION_ADJUSTMENT', 'TRANSFER_IN', 'TRANSFER_OUT', 'WITHDRAWAL'
            ]);

            // المبالغ (USD)
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);

            // معلومات الشراء بالعملة المحلية
            $table->decimal('local_amount', 14, 2)->nullable();
            $table->string('local_currency', 3)->nullable();
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates')->nullOnDelete();

            // ربط مع طلب الشراء
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();

            // مرفقات
            $table->string('invoice_image_url')->nullable();
            $table->string('receipt_image_url')->nullable();
            $table->string('proof_url')->nullable();

            // الوصف
            $table->text('description');
            $table->text('notes')->nullable();

            // من قام بالعملية
            $table->foreignId('performed_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();

            // تدقيق
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamp('transaction_date')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
            // ملاحظة: لا يوجد updated_at - الجدول Append-only

            $table->index(['buyer_account_id', 'transaction_date']);
            $table->index(['type', 'transaction_date']);
            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_transactions');
    }
};
