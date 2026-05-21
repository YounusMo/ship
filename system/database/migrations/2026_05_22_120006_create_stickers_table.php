<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single QR sticker. Encodes shipflow://qr/<sticker_id> as a ULID.
 *
 * Lifecycle:
 *   issued        — printed but not attached
 *   assigned      — attached to a specific shipment_piece
 *   revoked       — taken out of service (lost, damaged, misprinted)
 *
 * Per-piece scope: the FK is to shipment_pieces, not store_out_*. Multi-
 * box shipments get one sticker per box, matching the existing
 * shipment_pieces convention.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('stickers', function (Blueprint $t) {
            // Stickers use ULID as primary key so the QR encoding is opaque
            // and globally unique.
            $t->ulid('id')->primary();

            $t->foreignId('batch_id')
                ->constrained('sticker_batches')
                ->cascadeOnDelete();

            // Nullable until the sticker is assigned to a piece at scan time.
            $t->foreignId('shipment_piece_id')
                ->nullable()
                ->constrained('shipment_pieces')
                ->nullOnDelete();

            $t->timestamp('printed_at')->nullable();
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->string('revoke_reason', 200)->nullable();

            $t->timestamps();

            $t->index('shipment_piece_id');
            $t->index(['batch_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stickers');
    }
};
