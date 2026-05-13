<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a structured `purpose` code to clients_transactions.
 *
 * The veteran's complaint: 18 months in, every row's `notes` field is full
 * of typos, mixed-language text, and "see whatsapp" — unsearchable. The
 * dropdown forces the 95% common case into a known taxonomy, while `notes`
 * stays for the 5% of rows that need real free-text context.
 *
 * Existing rows get NULL — no backfill attempted. Operators can leave old
 * rows alone or annotate them later.
 *
 * Mirrored on treasury_transactions because the deposit/withdraw paths
 * write to both tables and we want consistent reporting.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients_transactions', function (Blueprint $t) {
            if (!Schema::hasColumn('clients_transactions', 'purpose')) {
                $t->string('purpose', 64)->nullable()->after('notes');
                $t->index('purpose');
            }
        });

        Schema::table('treasury_transactions', function (Blueprint $t) {
            if (!Schema::hasColumn('treasury_transactions', 'purpose')) {
                $t->string('purpose', 64)->nullable()->after('notes');
                $t->index('purpose');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients_transactions', function (Blueprint $t) {
            if (Schema::hasColumn('clients_transactions', 'purpose')) {
                $t->dropIndex(['purpose']);
                $t->dropColumn('purpose');
            }
        });

        Schema::table('treasury_transactions', function (Blueprint $t) {
            if (Schema::hasColumn('treasury_transactions', 'purpose')) {
                $t->dropIndex(['purpose']);
                $t->dropColumn('purpose');
            }
        });
    }
};
