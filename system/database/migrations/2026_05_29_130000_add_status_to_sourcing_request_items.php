<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Per-item delivery tracking. Lets the operator click through:
     *   pending → sourced → in_production → shipped → delivered
     * The proforma show page rolls these up into a "X of N delivered"
     * progress indicator.
     */
    public function up(): void
    {
        Schema::table('sourcing_request_items', function (Blueprint $t) {
            $t->enum('delivery_status', ['pending', 'sourced', 'in_production', 'shipped', 'delivered'])
              ->default('pending')
              ->after('cbm');
            $t->timestamp('status_changed_at')->nullable()->after('delivery_status');
        });
    }

    public function down(): void
    {
        Schema::table('sourcing_request_items', function (Blueprint $t) {
            $t->dropColumn(['delivery_status', 'status_changed_at']);
        });
    }
};
