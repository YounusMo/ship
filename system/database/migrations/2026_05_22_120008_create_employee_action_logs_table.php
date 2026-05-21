<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit log for every meaningful employee action in the
 * tracking subsystem: scan, custody handoff, sticker assignment, photo
 * upload. The intent is "I can reconstruct who did what when" — not
 * domain truth.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_action_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('branch_id')
                ->nullable()
                ->constrained('tracking_branches')
                ->nullOnDelete();
            $t->string('action', 64);
            $t->string('entity_type', 64)->nullable();
            $t->string('entity_id', 64)->nullable();
            $t->json('payload')->nullable();
            $t->string('ip_address', 64)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index(['user_id', 'created_at']);
            $t->index(['entity_type', 'entity_id']);
            $t->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_action_logs');
    }
};
