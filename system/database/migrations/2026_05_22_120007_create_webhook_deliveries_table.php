<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw landing pad for every inbound webhook (ShipsGo today, others later).
 * The controller returns HTTP 200 within ~100ms as soon as a row lands;
 * heavy work happens in a queued processor that reads from this table.
 *
 * Dedup is enforced by a unique index on (provider, external_event_id).
 * When the provider doesn't carry an event id (rare), the receiver fills
 * in sha256(payload) as the synthetic id. Either way, a duplicate POST
 * fails at insert time and we return 200 without dispatching a second
 * job. See docs/ALIGNMENT_PATCH.md §2.9.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 32)->index();

            // NOT NULL — synthesized as sha256(payload) when the upstream
            // didn't include one, so the unique index always applies.
            $t->string('external_event_id', 191);

            $t->string('event_type', 64)->nullable()->index();
            $t->json('payload');
            $t->string('signature', 500)->nullable();
            $t->boolean('signature_verified')->default(false);

            $t->timestamp('received_at')->useCurrent();
            $t->timestamp('processed_at')->nullable();
            $t->text('processing_error')->nullable();
            $t->unsignedTinyInteger('attempt_count')->default(0);

            $t->timestamps();

            $t->unique(
                ['provider', 'external_event_id'],
                'webhook_deliveries_provider_event_unique',
            );
            $t->index(['provider', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
