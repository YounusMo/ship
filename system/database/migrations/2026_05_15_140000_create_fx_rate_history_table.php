<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fx_rate_history', function (Blueprint $t) {
            $t->id();
            $t->string('currency', 8)->index();
            $t->decimal('rate', 18, 6);
            $t->decimal('previous_rate', 18, 6)->nullable();
            $t->unsignedBigInteger('set_by_user_id')->nullable()->index();
            $t->string('set_by_user_name', 191)->nullable();
            $t->timestamp('effective_from')->useCurrent()->index();
            $t->string('notes', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rate_history');
    }
};
