<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('exchange_rate_configs')->cascadeOnDelete();

            $table->string('from_currency', 3);
            $table->string('to_currency', 3);

            // السعر الخام
            $table->decimal('raw_rate', 18, 8);
            $table->string('raw_source', 50);
            $table->timestamp('raw_fetched_at');

            // الهامش
            $table->enum('margin_type', ['NONE', 'PERCENTAGE', 'FIXED_AMOUNT']);
            $table->decimal('margin_value', 8, 4);
            $table->decimal('margin_amount', 18, 8);

            // السعر النهائي
            $table->decimal('effective_rate', 18, 8);

            // التعديل اليدوي
            $table->boolean('is_manual_override')->default(false);
            $table->foreignId('override_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('override_reason')->nullable();

            // الحالة
            $table->enum('status', ['ACTIVE', 'SUPERSEDED', 'PENDING_APPROVAL', 'REJECTED'])
                ->default('ACTIVE');
            $table->timestamp('valid_from')->useCurrent();
            $table->timestamp('valid_until')->nullable();

            // الموافقة
            $table->boolean('requires_approval')->default(false);
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index(['from_currency', 'to_currency', 'status']);
            $table->index('valid_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
