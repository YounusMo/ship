<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_counts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('branch_id')->index();
            $t->string('currency', 8);
            $t->decimal('system_balance', 18, 4);
            $t->decimal('counted_amount', 18, 4);
            $t->decimal('variance', 18, 4);
            $t->date('count_date')->index();
            $t->unsignedBigInteger('counted_by_user_id')->nullable();
            $t->string('counted_by_user_name', 191)->nullable();
            $t->timestamp('counted_at')->useCurrent();
            $t->string('notes', 500)->nullable();
            $t->boolean('adjustment_posted')->default(false);
            $t->unsignedBigInteger('adjustment_transaction_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_counts');
    }
};
