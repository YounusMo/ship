<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The append-only spine of the tracking subsystem. Every state change —
 * whether sourced from ShipsGo (kind=INTERNATIONAL) or from an employee
 * scan (kind=INTERNAL) — lands here in one stream. UnifiedTimelineService
 * reads this table to build the customer-facing timeline.
 *
 * Polymorphic shipment ref via (shipment_source_table, shipment_source_id)
 * matches the convention already used by shipment_pieces, so the same
 * rows can address sea (store_out_sea) or sky (store_out_sky) shipments
 * without a discriminator column on every consumer.
 *
 * Idempotency: events arriving twice with the same client_event_id within
 * a (kind, client_event_id) combination collapse to one row — see the
 * unique index. Without a client_event_id, the row is always inserted.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tracking_events', function (Blueprint $t) {
            $t->id();

            // What shipment this event belongs to (polymorphic).
            $t->string('shipment_source_table', 32);
            $t->unsignedBigInteger('shipment_source_id');

            // Optional pin to a specific piece (when known).
            $t->foreignId('shipment_piece_id')
                ->nullable()
                ->constrained('shipment_pieces')
                ->nullOnDelete();

            $t->enum('kind', ['INTERNATIONAL', 'INTERNAL'])->index();

            // Event code — e.g. GATE_IN, LOADED, ARRIVED, RECEIVED_AT_BRANCH.
            // String (not enum) so adding a new event type doesn't require
            // a migration.
            $t->string('event_type', 64);

            $t->timestamp('occurred_at')->index();

            // Location at time of event.
            $t->string('city', 128)->nullable();
            $t->string('country', 64)->nullable();

            // For INTERNAL events, the branch the scan happened at.
            $t->foreignId('branch_id')
                ->nullable()
                ->constrained('tracking_branches')
                ->nullOnDelete();

            // Provider raw payload (ShipsGo) or scan metadata (photos, notes).
            $t->json('raw_payload')->nullable();

            // Localization handled at the backend — the client gets ready-to-
            // render Arabic/English via translation_key + params.
            $t->string('translation_key', 191)->nullable();
            $t->json('translation_params')->nullable();

            // Who wrote it (for INTERNAL — null for ShipsGo).
            $t->foreignId('recorded_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Caller-supplied dedup key (header Idempotency-Key on scan
            // submits; the ShipsGo event id for webhooks).
            $t->string('client_event_id', 191)->nullable();

            // Whether this event is currently shown to the customer.
            // Some intermediate events stay internal-only.
            $t->boolean('is_customer_visible')->default(true)->index();

            $t->timestamps();

            $t->index(
                ['shipment_source_table', 'shipment_source_id', 'occurred_at'],
                'tracking_events_shipment_occurred_idx',
            );
            $t->index(['kind', 'event_type']);

            // Idempotency: at most one event per (kind, client_event_id)
            // when an id is supplied. Without an id, no constraint.
            $t->unique(
                ['kind', 'client_event_id'],
                'tracking_events_kind_client_event_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
};
