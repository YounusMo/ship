<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipment_pieces', function (Blueprint $t) {
            $t->charset   = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';

            $t->id();

            // Public-facing scannable code. Crockford-base32, 12 chars, with
            // a "MTZ-" prefix and dashes inserted for readability when shown
            // (storage is the raw 12 chars, formatting happens at render).
            // 32^12 ≈ 1.2e18 — collision-safe well past any realistic volume.
            $t->string('tracking_code', 24)->unique();

            // What ledger row this piece belongs to. We point at store_sea
            // / store_sky directly rather than going through containers,
            // because pieces exist as soon as goods are received — long
            // before they're loaded into a container.
            $t->string('source_table', 32);   // store_sea | store_sky
            $t->unsignedBigInteger('source_id');

            // No FK: clients can be soft-deleted by operators and we don't
            // want a stale row to break sticker printing. Nulled out values
            // are handled at the render layer.
            $t->unsignedBigInteger('client_id')->nullable()->index();

            // Position within the batch (1..N) and snapshot of the original
            // count. We snapshot piece_total so that if the operator later
            // edits store_sea.number we don't silently invalidate already-
            // printed stickers.
            $t->unsignedSmallInteger('piece_index');
            $t->unsignedSmallInteger('piece_total');

            // 'active' once generated; flipped to 'cancelled' when the
            // source row is cancelled, or when piece_total shrinks via edit.
            $t->enum('status', ['active', 'cancelled'])->default('active');

            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();

            // A given source row can only have piece N once.
            $t->unique(['source_table', 'source_id', 'piece_index'], 'shipment_pieces_src_idx_unique');

            // Hot read path: "all active pieces for this source row"
            // (sticker PDF, public tracking, ensurePiecesExist).
            $t->index(['source_table', 'source_id', 'status'], 'shipment_pieces_src_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_pieces');
    }
};
