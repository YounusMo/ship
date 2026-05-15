<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $t) {
            $t->id();
            $t->smallInteger('period_year');
            $t->tinyInteger('period_month');
            $t->date('period_start');
            $t->date('period_end');
            $t->enum('status', ['open', 'closed'])->default('open');
            $t->unsignedBigInteger('closed_by_user_id')->nullable();
            $t->string('closed_by_user_name', 191)->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->unsignedBigInteger('reopened_by_user_id')->nullable();
            $t->string('reopened_by_user_name', 191)->nullable();
            $t->timestamp('reopened_at')->nullable();
            $t->string('notes', 500)->nullable();
            $t->timestamps();
            $t->unique(['period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
