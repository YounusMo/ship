<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Track when the client first opened the share link, and how many
     * times in total. Useful "did the client even look at it?" signal
     * when chasing approvals.
     */
    public function up(): void
    {
        Schema::table('sourcing_requests', function (Blueprint $t) {
            $t->timestamp('client_viewed_at')->nullable()->after('sent_at');
            $t->unsignedInteger('client_view_count')->default(0)->after('client_viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('sourcing_requests', function (Blueprint $t) {
            $t->dropColumn(['client_viewed_at', 'client_view_count']);
        });
    }
};
