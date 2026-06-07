<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tracking_idempotency_keys table was created for an HTTP-header
 * Idempotency-Key middleware that never shipped. In its place, dedup
 * runs at the persistence layer via the unique index on
 * tracking_events.(kind, client_event_id) — which has been load-bearing
 * since Phase 2 and is exercised by ShipsGoWebhookTest::test_job_is_idempotent_on_replay
 * and EmployeeApiTest::test_submit_uses_client_event_id_for_idempotency.
 *
 * Dropping the unused table to keep the schema honest. If the
 * middleware-layer approach is revived later, recreate the table with a
 * fresh up() migration; no data to preserve.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('tracking_idempotency_keys');
    }

    public function down(): void
    {
        // Recreate the original shape so `migrate:rollback` to before
        // 2026_05_22_120010 stays consistent. No data is restored.
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
};
