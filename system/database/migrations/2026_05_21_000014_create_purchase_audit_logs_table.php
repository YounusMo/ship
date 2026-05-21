<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->string('entity_type', 100);
            $table->string('entity_id', 50);

            $table->string('action', 50);

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changes')->nullable();

            $table->foreignId('performed_by_id')->constrained('users')->restrictOnDelete();
            $table->string('user_role', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('performed_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['performed_by_id', 'performed_at']);
            $table->index(['action', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_audit_logs');
    }
};
