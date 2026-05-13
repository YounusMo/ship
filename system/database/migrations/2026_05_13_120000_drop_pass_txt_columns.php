<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-008: drop the cleartext `pass_txt` columns from users and clients.
 *
 * The columns were written alongside the bcrypt `password` hash, defeating the
 * point of hashing whenever the DB or a backup leaked. The application no
 * longer writes pass_txt as of the same release that ships this migration.
 *
 * This migration NULLs any remaining values first so even an interrupted
 * migration (or a rollback) leaves no cleartext behind, then drops the column.
 *
 * IMPORTANT: existing `cron_jobs/backups/*.sql` files still contain the old
 * cleartext values — take a fresh backup AFTER this runs and delete the old
 * ones.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['users', 'clients'] as $table) {
            if (Schema::hasColumn($table, 'pass_txt')) {
                DB::table($table)->update(['pass_txt' => null]);
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('pass_txt');
                });
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-restorative — re-adding the column would re-create
        // the storage slot but the cleartext data is gone. Provided so that
        // `migrate:rollback` does not fail.
        foreach (['users', 'clients'] as $table) {
            if (!Schema::hasColumn($table, 'pass_txt')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('pass_txt')->nullable();
                });
            }
        }
    }
};
