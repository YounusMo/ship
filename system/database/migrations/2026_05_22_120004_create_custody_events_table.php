<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custody chain — who physically holds the shipment right now and how it
 * got there. Each row is one hand-off (HUB → SPOKE, courier → branch,
 * branch → customer). The CURRENT custody is the most recent row per
 * (shipment_source_table, shipment_source_id, shipment_piece_id).
 *
 * Distinct from tracking_events: tracking_events is what we SHOW the
 * customer, custody_events is operational truth used by routing logic
 * and EnforceBranchScope middleware to gate scans.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('custody_events', function (Blueprint $t) {
            $t->id();

            $t->string('shipment_source_table', 32);
            $t->unsignedBigInteger('shipment_source_id');
            $t->foreignId('shipment_piece_id')
                ->nullable()
                ->constrained('shipment_pieces')
                ->nullOnDelete();

            $t->enum('event_type', [
                'RECEIVED_AT_HUB',
                'DISPATCHED',
                'RECEIVED_AT_BRANCH',
                'READY_FOR_PICKUP',
                'DELIVERED_TO_CUSTOMER',
                'RETURNED_TO_HUB',
                'LOST',
                'DAMAGED',
            ]);

            $t->foreignId('from_branch_id')
                ->nullable()
                ->constrained('tracking_branches')
                ->nullOnDelete();
            $t->foreignId('to_branch_id')
                ->nullable()
                ->constrained('tracking_branches')
                ->nullOnDelete();

            $t->foreignId('recorded_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $t->timestamp('occurred_at');

            $t->json('photos')->nullable();
            $t->string('notes', 1000)->nullable();

            // The corresponding tracking_events row (if one was emitted).
            $t->foreignId('tracking_event_id')
                ->nullable()
                ->constrained('tracking_events')
                ->nullOnDelete();

            $t->timestamps();

            $t->index(
                ['shipment_source_table', 'shipment_source_id', 'occurred_at'],
                'custody_events_shipment_occurred_idx',
            );
            $t->index(['to_branch_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custody_events');
    }
};
