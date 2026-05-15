<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prepayments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('client_id')->index();
            $t->unsignedBigInteger('source_transaction_id')->unique();
            $t->string('currency', 8);
            $t->decimal('original_amount', 18, 4);
            $t->decimal('applied_amount', 18, 4)->default(0);
            $t->decimal('remaining_amount', 18, 4);
            $t->enum('status', ['open', 'fully_applied', 'cancelled'])->default('open')->index();
            $t->date('received_date')->index();
            $t->unsignedBigInteger('created_by_user_id')->nullable();
            $t->timestamps();
        });

        Schema::create('prepayment_applications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('prepayment_id')->index();
            $t->decimal('amount', 18, 4);
            $t->string('applied_to_ref', 191)->nullable();
            $t->string('applied_to_type', 64)->nullable();
            $t->unsignedBigInteger('applied_to_id')->nullable();
            $t->string('notes', 500)->nullable();
            $t->unsignedBigInteger('applied_by_user_id')->nullable();
            $t->string('applied_by_user_name', 191)->nullable();
            $t->timestamp('applied_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prepayment_applications');
        Schema::dropIfExists('prepayments');
    }
};
