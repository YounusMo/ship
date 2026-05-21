<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-actor idempotency cache for write endpoints. A scan submit sent
 * twice with the same Idempotency-Key header returns the cached response
 * instead of re-applying the side effect. TTL 24h, swept by a scheduled
 * job. See docs/ALIGNMENT_PATCH.md §2.11.
 *
 * actor_type / actor_id is polymorphic so the same table covers
 * employee writes (actor_type=user) and customer writes (actor_type=client)
 * when those land in Phase 5.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tracking_idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->string('actor_type', 16);
            $t->unsignedBigInteger('actor_id');
            $t->string('key', 191);
            $t->string('endpoint', 191);
            $t->unsignedSmallInteger('response_status');
            $t->longText('response_body');
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('expires_at')->index();

            $t->unique(
                ['actor_type', 'actor_id', 'endpoint', 'key'],
                'tracking_idem_actor_endpoint_key_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_idempotency_keys');
    }
};
