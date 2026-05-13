<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * audit_log: append-only record of financial / account mutations.
 *
 * The single most-asked question after a discrepancy in this business is
 * "who changed this and when?". The system had no answer. This table is
 * the answer.
 *
 * Design notes:
 *  - Append-only by convention. No application code should UPDATE or DELETE
 *    rows here. Treat the table as a logbook.
 *  - Writes happen inside the same DB transaction as the change they record,
 *    so a rolled-back business operation also rolls back its audit row.
 *  - `payload` is hand-curated by each call site, not auto-introspected,
 *    because the codebase uses the Query Builder, not Eloquent (no model
 *    events to hook into). Each controller decides what's worth recording.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('user_type', 32)->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->string('action', 48);
            $t->string('target_table', 64);
            $t->unsignedBigInteger('target_id')->nullable();
            $t->json('payload')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('context', 191)->nullable();
            $t->dateTime('created_at');

            $t->index(['target_table', 'target_id']);
            $t->index(['user_id', 'created_at']);
            $t->index(['action', 'created_at']);
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
