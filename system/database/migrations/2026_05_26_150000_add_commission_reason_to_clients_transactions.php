<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * `clients_transactions.commission` is a number with no label.
     * Operators charge commissions for many different reasons (wire-transfer
     * fee, FX margin, processing, late-payment fee, etc.) and the
     * accountant has been guessing from the surrounding notes.
     *
     * This column stores the operator-supplied reason at the time of the
     * deposit/withdraw, so the audit trail and the journal_lines.description
     * have something concrete to point at.
     */
    public function up(): void
    {
        Schema::table('clients_transactions', function (Blueprint $t) {
            $t->string('commission_reason', 191)->nullable()->after('commission');
        });
    }

    public function down(): void
    {
        Schema::table('clients_transactions', function (Blueprint $t) {
            $t->dropColumn('commission_reason');
        });
    }
};
