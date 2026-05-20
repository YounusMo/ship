<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-client notification preferences — one switch per category the
 * mobile app surfaces (transactions, shipments, receipts).
 *
 * Defaults to true on every column so existing clients keep getting
 * notifications. The mobile Settings screen toggles these via
 * GET/PATCH /api/notifications/prefs.
 *
 * Gating happens server-side inside each Notification class's via():
 * a muted category returns [] so neither the database row nor the FCM
 * payload is created. The unread badge therefore stays in sync with
 * what the user actually opted into.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Idempotent: a dev sandbox may have already added these.
            if (!Schema::hasColumn('clients', 'notify_transactions')) {
                $table->boolean('notify_transactions')->default(true)->after('lang');
            }
            if (!Schema::hasColumn('clients', 'notify_shipments')) {
                $table->boolean('notify_shipments')->default(true)->after('notify_transactions');
            }
            if (!Schema::hasColumn('clients', 'notify_receipts')) {
                $table->boolean('notify_receipts')->default(true)->after('notify_shipments');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach (['notify_transactions', 'notify_shipments', 'notify_receipts'] as $col) {
                if (Schema::hasColumn('clients', $col)) $table->dropColumn($col);
            }
        });
    }
};
