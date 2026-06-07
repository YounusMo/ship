<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Full-snapshot versioning for proformas. Every time something
     * meaningful changes (manual snapshot, sent, approved, fulfilled,
     * markup applied), we freeze the entire shape — proforma fields +
     * items + payments — into a single JSON blob keyed by version_no.
     *
     * The blob is large but versioning is rare; storage cost is dwarfed
     * by the value of being able to say "this is what the client saw
     * when they approved on date X". snapshot_json is JSON column so
     * future queries can drill in without parsing.
     */
    public function up(): void
    {
        Schema::create('sourcing_request_versions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sourcing_request_id')->index();
            $t->unsignedInteger('version_no')->index();
            $t->enum('trigger', [
                'manual', 'sent', 'approved', 'fulfilled',
                'markup_applied', 'plan_regenerated', 'cloned_from',
            ])->index();
            $t->string('label', 191)->nullable();
            $t->json('snapshot_json');

            // Denormalised summary fields for the index list — avoids
            // parsing the blob for every row in the version history table.
            $t->string('status_at_snapshot', 32);
            $t->decimal('total_at_snapshot', 18, 4)->default(0);
            $t->string('currency_at_snapshot', 8)->nullable();
            $t->unsignedInteger('item_count_at_snapshot')->default(0);

            $t->unsignedBigInteger('created_by_user_id')->nullable();
            $t->timestamps();

            $t->unique(['sourcing_request_id', 'version_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sourcing_request_versions');
    }
};
