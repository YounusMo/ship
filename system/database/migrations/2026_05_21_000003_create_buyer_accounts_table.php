<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('buyer_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->unique()->constrained('buyers')->cascadeOnDelete();

            // كل الأرصدة بالـ USD
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('reserved_balance', 14, 2)->default(0);
            $table->decimal('total_spent', 14, 2)->default(0);
            $table->decimal('total_received', 14, 2)->default(0);

            $table->decimal('min_threshold', 14, 2)->default(1000);

            $table->timestamp('last_reconciled_at')->nullable();
            $table->foreignId('last_reconciled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_accounts');
    }
};
