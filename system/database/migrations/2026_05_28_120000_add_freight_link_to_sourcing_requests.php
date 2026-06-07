<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Linkage from a proforma to the freight container that ships it.
     * The proforma describes the goods + payment; the container describes
     * the shipment + carrier costs. Separate concerns, but the operator
     * shouldn't re-key client / items / weights twice. These columns are
     * the back-pointer that lets the proforma show "shipped on container
     * #X" and the container show "originated from proforma #Y".
     */
    public function up(): void
    {
        Schema::table('sourcing_requests', function (Blueprint $t) {
            $t->enum('freight_kind', ['sky', 'sea'])->nullable()->after('purchase_order_id');
            $t->unsignedBigInteger('freight_container_id')->nullable()->after('freight_kind');
            $t->index(['freight_kind', 'freight_container_id'], 'sourcing_requests_freight_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sourcing_requests', function (Blueprint $t) {
            $t->dropIndex('sourcing_requests_freight_idx');
            $t->dropColumn(['freight_kind', 'freight_container_id']);
        });
    }
};
