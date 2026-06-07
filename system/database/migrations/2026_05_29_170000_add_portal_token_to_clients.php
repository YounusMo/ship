<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Per-client portal token. The client gets one URL like:
     *
     *   /portal/{token}
     *
     * that lists every proforma we have for them. Distinct from the
     * per-proforma share_token so the client can use this URL forever
     * (until rotated) and see new proformas as they appear.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $t) {
            $t->string('portal_token', 64)->nullable()->unique();
            $t->timestamp('portal_token_issued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $t) {
            $t->dropUnique(['portal_token']);
            $t->dropColumn(['portal_token', 'portal_token_issued_at']);
        });
    }
};
