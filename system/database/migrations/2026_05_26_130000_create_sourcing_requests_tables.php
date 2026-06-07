<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Sourcing requests — clients ask us to find a product in a target
     * market and we charge a commission ("عمولة البحث عن البضاعة").
     *
     * The commission lands in CoA 4020 (Sourcing commission revenue).
     * One commission posting per request — locked by the
     * commission_journal_entry_id pointer so the controller can't
     * double-post. The cash settlement is a separate flow (existing
     * client deposit/withdraw rails) — we only record the revenue
     * accrual here against AR clients.
     *
     * Status flow:
     *   open → searching → quoted → accepted → fulfilled
     *   (any) → canceled
     */
    public function up(): void
    {
        Schema::create('sourcing_requests', function (Blueprint $t) {
            $t->id();
            $t->string('request_number', 64)->unique();
            $t->unsignedBigInteger('client_id')->index();
            $t->unsignedBigInteger('branch_id')->nullable()->index();

            $t->string('title', 255);
            $t->text('description')->nullable();

            // Target the client gave us — informational, not transactional.
            $t->decimal('target_quantity', 18, 4)->nullable();
            $t->string('target_unit', 32)->nullable();
            $t->decimal('target_unit_price', 18, 4)->nullable();
            $t->string('currency', 8);

            $t->enum('status', [
                'open', 'searching', 'quoted', 'accepted', 'fulfilled', 'canceled',
            ])->default('open')->index();

            // Commission — set when the client accepts a quote. Idempotency
            // pivot lives on commission_journal_entry_id, NOT on a separate
            // flag, so a partial failure can't leave us with the flag set
            // but no journal row.
            $t->decimal('commission_amount', 18, 4)->nullable();
            $t->string('commission_currency', 8)->nullable();
            $t->timestamp('commission_posted_at')->nullable();
            $t->unsignedBigInteger('commission_journal_entry_id')->nullable()->index();

            // Optional link to a purchase order, when sourcing converts.
            $t->unsignedBigInteger('purchase_order_id')->nullable()->index();

            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();

            $t->index(['client_id', 'status']);
            $t->index(['branch_id', 'status']);
        });

        Schema::create('sourcing_request_quotes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sourcing_request_id')->index();

            // Either a supplier in our DB OR a free-form name when we're
            // sourcing from a vendor we haven't onboarded.
            $t->unsignedBigInteger('supplier_id')->nullable()->index();
            $t->string('supplier_name_freeform', 191)->nullable();

            $t->decimal('unit_price', 18, 4);
            $t->decimal('quantity',   18, 4)->nullable();
            $t->decimal('total_price', 18, 4);
            $t->string('currency', 8);
            $t->unsignedInteger('lead_time_days')->nullable();
            $t->text('notes')->nullable();

            $t->enum('status', ['proposed', 'accepted', 'rejected'])->default('proposed')->index();

            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sourcing_request_quotes');
        Schema::dropIfExists('sourcing_requests');
    }
};
