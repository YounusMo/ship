<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The container detail view (pages/shipping/.../containers/container_data.blade.php)
 * branches on $item->payment_pending === 'pending' to decide between the
 * "Pending payment" and the regular "Payment" button. The column was
 * referenced but never created, so the page 500s as soon as a container
 * has any outbound items.
 *
 * Default is empty string (matches the existing free-form string convention
 * — other status columns in these tables are stored as text like
 * 'true' / 'false').
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['store_out_sea', 'store_out_sky'] as $table) {
            if (!Schema::hasColumn($table, 'payment_pending')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('payment_pending', 32)->nullable()->default(null)->after('payment');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['store_out_sea', 'store_out_sky'] as $table) {
            if (Schema::hasColumn($table, 'payment_pending')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('payment_pending');
                });
            }
        }
    }
};
