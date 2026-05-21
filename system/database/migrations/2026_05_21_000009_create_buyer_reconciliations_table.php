<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('buyer_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_account_id')->constrained('buyer_accounts')->cascadeOnDelete();

            // الفترة
            $table->date('period_start');
            $table->date('period_end');

            // الأرصدة
            $table->decimal('system_balance', 14, 2);
            $table->decimal('actual_balance', 14, 2);
            $table->decimal('difference', 14, 2);

            $table->text('difference_reason')->nullable();
            $table->foreignId('adjustment_tx_id')->nullable()->constrained('buyer_transactions')->nullOnDelete();

            $table->string('attachment_url')->nullable();

            $table->foreignId('reconciled_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', ['PENDING_APPROVAL', 'APPROVED', 'REJECTED'])->default('PENDING_APPROVAL');

            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamp('reconciled_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_account_id', 'period_end']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_reconciliations');
    }
};
