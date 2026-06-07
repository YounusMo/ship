<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 15 — Health Watch.
 *
 * One row per (proforma, calendar date). The daily artisan command
 * upserts here so operators can watch the trend, not just today's
 * number. Kept narrow on purpose — full per-day factor JSON is small
 * enough not to need a separate table, and we hard-cap retention from
 * the cleanup pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sourcing_deal_health_snapshots', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sourcing_request_id');
            $t->date('snapshot_date');
            $t->unsignedTinyInteger('score');
            $t->json('factors')->nullable();
            $t->timestamp('computed_at')->useCurrent();

            $t->unique(['sourcing_request_id', 'snapshot_date'], 'sdhs_proforma_date_uq');
            $t->index('snapshot_date', 'sdhs_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sourcing_deal_health_snapshots');
    }
};
