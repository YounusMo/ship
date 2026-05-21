<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exchange_rate_configs', function (Blueprint $table) {
            $table->id();

            $table->string('from_currency', 3);
            $table->string('to_currency', 3);

            $table->enum('source', ['API', 'MANUAL', 'HYBRID'])->default('API');
            $table->string('primary_provider', 50)->default('openexchangerates');
            $table->string('fallback_provider', 50)->nullable()->default('frankfurter');

            $table->enum('margin_type', ['NONE', 'PERCENTAGE', 'FIXED_AMOUNT'])->default('PERCENTAGE');
            $table->decimal('margin_value', 8, 4)->default(0);

            $table->boolean('auto_update')->default(true);
            $table->unsignedTinyInteger('update_interval_hours')->default(6);

            $table->decimal('max_deviation_pct', 5, 2)->nullable()->default(5.00);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['from_currency', 'to_currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_configs');
    }
};
