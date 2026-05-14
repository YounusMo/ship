<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `calc` flag column the controllers expect but the production
 * dump never had. Six PHP files reference it:
 *
 *   clientsController.php:531  whereNull('calc')
 *   clientsController.php:804  'calc' => $request->old_balance === "true" ? 'false' : null
 *   branchesController.php:183 whereNull('calc')
 *   branchesController.php:275 whereNull('calc')
 *   oldBalanceArchiveController.php:19  where('calc','false')
 *   reconciliationController.php:99 whereNull('calc')
 *
 * The column stores either NULL (normal transaction) or the literal string
 * 'false' (transaction is from the legacy old-balance import and should be
 * excluded from balance recomputation). VARCHAR(8) covers both.
 *
 * Without this column every deposit/withdraw silently 500s at the
 * branchesController::update_balance() step because the SELECT in
 * update_balance() can't find `calc`.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('clients_transactions')
            && ! Schema::hasColumn('clients_transactions', 'calc')) {
            Schema::table('clients_transactions', function (Blueprint $t) {
                $t->string('calc', 8)->nullable()->after('purpose');
                $t->index('calc');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clients_transactions')
            && Schema::hasColumn('clients_transactions', 'calc')) {
            Schema::table('clients_transactions', function (Blueprint $t) {
                $t->dropIndex(['calc']);
                $t->dropColumn('calc');
            });
        }
    }
};
