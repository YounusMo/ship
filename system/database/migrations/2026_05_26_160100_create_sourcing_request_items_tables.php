<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Proforma line items + photo gallery. One sourcing_request has many
     * items, each item has many photos (one flagged is_primary for use as
     * a thumbnail in tables and the PDF).
     *
     * Items are denominated in their own currency (supplier-side, often
     * CNY when sourcing from China). Conversion to the proforma display
     * currency happens at presentation time using the frozen FX snapshot
     * on the parent sourcing_request.
     */
    public function up(): void
    {
        Schema::create('sourcing_request_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sourcing_request_id')->index();

            $t->string('name', 191);
            $t->string('code', 64)->nullable();    // SKU / HS code / supplier code
            $t->text('description')->nullable();

            $t->decimal('quantity', 18, 4)->default(1);
            $t->string('unit', 32)->default('pcs');

            // What the supplier charges us per unit. Currency can differ
            // per item — sourcing from multiple suppliers in different
            // currencies inside one proforma is a real scenario.
            $t->decimal('unit_cost', 18, 4)->default(0);
            $t->string('unit_cost_currency', 8)->default('usd');

            // What we sell each unit for. When commission_mode='hidden_in_prices'
            // this is unit_cost + per-unit margin; when visible_separate this
            // typically equals unit_cost and the commission is shown as a
            // separate line on the proforma.
            $t->decimal('unit_price_to_client', 18, 4)->default(0);

            // Freight estimation inputs — we'll need these for the air/sea
            // handoff so the operator doesn't re-enter them.
            $t->decimal('weight_kg', 18, 4)->nullable();
            $t->decimal('cbm', 18, 4)->nullable();

            $t->unsignedSmallInteger('sort_order')->default(0);

            // Optional back-pointer to the internal supplier quote this
            // item was sourced from. NULL when the operator typed in the
            // item without first logging a supplier quote.
            $t->unsignedBigInteger('source_quote_id')->nullable()->index();

            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();

            $t->index(['sourcing_request_id', 'sort_order']);
        });

        Schema::create('sourcing_request_item_photos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('item_id')->index();

            // Public path under storage/app/public — accessed via
            // /storage/proforma/... when the symlink is set up.
            $t->string('path', 255);
            $t->string('original_name', 191)->nullable();
            $t->unsignedInteger('size_bytes')->nullable();
            $t->string('mime', 64)->nullable();

            // Exactly one row per item should have is_primary=true. We use
            // a partial-uniqueness pattern in the controller, not a DB
            // constraint (MySQL can't express "unique where flag=true").
            $t->boolean('is_primary')->default(false);
            $t->string('caption', 191)->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);

            $t->unsignedBigInteger('uploaded_by')->nullable();
            $t->timestamps();

            $t->index(['item_id', 'is_primary']);
            $t->index(['item_id', 'sort_order']);
        });

        Schema::create('sourcing_request_payments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sourcing_request_id')->index();

            // 1-based position in the schedule. Lets the operator reorder
            // installments and the UI render them in a stable order.
            $t->unsignedSmallInteger('sequence')->default(1);
            $t->string('label', 191);          // "Deposit 30%", "On delivery", ...

            // Percentage IS the source of truth when the plan auto-generates
            // — amount is recomputed. After manual edits, amount sticks and
            // percentage becomes a display-only hint.
            $t->decimal('percentage', 8, 4)->default(0);
            $t->decimal('amount', 18, 4)->default(0);
            $t->string('currency', 8);

            $t->date('due_date')->nullable();

            // scheduled → partial / paid (terminal)  |  canceled (terminal)
            $t->enum('status', ['scheduled', 'partial', 'paid', 'canceled'])
              ->default('scheduled')
              ->index();

            $t->decimal('paid_amount', 18, 4)->default(0);
            $t->timestamp('settled_at')->nullable();

            // Linkback to the clients_transactions row that actually moved
            // the money. NULL until a payment is recorded against this row.
            $t->unsignedBigInteger('settled_by_transaction_id')->nullable()->index();

            $t->string('notes', 500)->nullable();

            $t->timestamps();

            $t->index(['sourcing_request_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sourcing_request_payments');
        Schema::dropIfExists('sourcing_request_item_photos');
        Schema::dropIfExists('sourcing_request_items');
    }
};
