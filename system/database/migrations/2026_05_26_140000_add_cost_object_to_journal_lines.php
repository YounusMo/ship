<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add a polymorphic cost-object dimension to journal_lines so
     * per-flight / per-container / per-PO / per-sourcing-request
     * profitability can be sliced straight from the ledger.
     *
     *  cost_object_type — short tag like 'flight', 'container_sea',
     *                     'purchase_order', 'sourcing_request', 'shipment'
     *  cost_object_id   — id of the row in the corresponding table
     *
     * Distinct from counterparty (who) and source_table/source_id (which
     * ledger row produced this entry). The cost-object answers "which
     * deliverable is this revenue or expense attached to?".
     *
     * Existing rows pass through with NULL — that's correct: they were
     * posted before this dimension existed. Reports tolerate NULL.
     */
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $t) {
            $t->string('cost_object_type', 32)->nullable()->after('counterparty_id');
            $t->unsignedBigInteger('cost_object_id')->nullable()->after('cost_object_type');
            $t->index(['cost_object_type', 'cost_object_id'], 'journal_lines_cost_object_idx');
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $t) {
            $t->dropIndex('journal_lines_cost_object_idx');
            $t->dropColumn(['cost_object_type', 'cost_object_id']);
        });
    }
};
