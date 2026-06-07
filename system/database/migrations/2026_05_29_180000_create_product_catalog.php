<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Reusable product library. Lets the operator pick from a catalog
     * when adding items to a new proforma instead of re-typing the same
     * widget every time. Defaults flow through to the proforma item;
     * the operator can still tweak per-proforma.
     *
     * Single primary photo only — keeping this simple. Item-level photo
     * galleries still live on sourcing_request_item_photos so per-deal
     * adjustments don't affect the catalog entry.
     */
    public function up(): void
    {
        Schema::create('product_catalog', function (Blueprint $t) {
            $t->id();
            $t->string('name', 191);
            $t->string('code', 64)->nullable()->index();
            $t->text('description')->nullable();
            $t->string('unit', 32)->default('pcs');
            $t->decimal('default_unit_cost', 18, 4)->default(0);
            $t->string('default_unit_cost_currency', 8)->default('usd');
            $t->decimal('default_unit_price', 18, 4)->default(0);
            $t->decimal('default_weight_kg', 18, 4)->nullable();
            $t->decimal('default_cbm', 18, 4)->nullable();
            $t->string('photo_path', 255)->nullable();
            $t->boolean('is_active')->default(true);

            // Usage tracking — sort the picker by most-used so common
            // items float to the top.
            $t->unsignedInteger('usage_count')->default(0);
            $t->timestamp('last_used_at')->nullable();

            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();

            $t->index(['is_active', 'usage_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_catalog');
    }
};
