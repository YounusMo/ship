<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->string('code', 20)->unique();
            $table->string('full_name');
            $table->string('phone', 30);

            $table->foreignId('primary_warehouse_id')->constrained('warehouses')->restrictOnDelete();

            $table->decimal('max_order_value', 14, 2)->default(5000.00);
            $table->boolean('can_approve_orders')->default(false);

            $table->enum('status', ['ACTIVE', 'SUSPENDED', 'TERMINATED'])->default('ACTIVE');
            $table->timestamp('hired_at')->useCurrent();
            $table->timestamp('terminated_at')->nullable();

            $table->timestamps();

            $table->index(['primary_warehouse_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyers');
    }
};
