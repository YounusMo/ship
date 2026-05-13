<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the clients_transactions.purpose column on the other three
 * transaction tables: branches, suppliers, customs_brokers. Same pattern,
 * same nullable shape, same backfill stance (existing rows stay NULL).
 *
 * Done as a separate migration so it can be applied independently of
 * the clients_transactions one if needed.
 */
return new class extends Migration {

    private const TABLES = [
        'branches_transactions',
        'suppliers_transactions',
        'customs_brokers_transactions',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'purpose')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('purpose', 64)->nullable()->after('notes');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'purpose')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('purpose');
                });
            }
        }
    }
};
