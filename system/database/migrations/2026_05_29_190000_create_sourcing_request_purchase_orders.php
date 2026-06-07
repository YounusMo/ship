<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Many-to-many link between proformas and purchase orders. A single
     * proforma can be fulfilled by multiple POs (e.g. items split across
     * suppliers), and a single PO can serve multiple proformas in rare
     * recurring-buyer cases.
     *
     * Item delivery dates live on sourcing_request_items separately —
     * this table just records the relationship and an optional note
     * (e.g. "PO covers items 1-3 only").
     */
    public function up(): void
    {
        Schema::create('sourcing_request_purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sourcing_request_id')->index();
            $t->unsignedBigInteger('purchase_order_id')->index();
            $t->string('note', 500)->nullable();
            $t->unsignedBigInteger('linked_by_user_id')->nullable();
            $t->timestamps();

            $t->unique(['sourcing_request_id', 'purchase_order_id'], 'sr_po_unique');
        });

        // Per-item delivery commitments. promised_delivery_date is what we
        // told the client; supplier_confirmed_date is what the supplier
        // actually committed to. Reports compare the two and flag slips.
        Schema::table('sourcing_request_items', function (Blueprint $t) {
            $t->date('promised_delivery_date')->nullable()->after('delivery_status');
            $t->date('supplier_confirmed_date')->nullable()->after('promised_delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('sourcing_request_items', function (Blueprint $t) {
            $t->dropColumn(['promised_delivery_date', 'supplier_confirmed_date']);
        });
        Schema::dropIfExists('sourcing_request_purchase_orders');
    }
};
