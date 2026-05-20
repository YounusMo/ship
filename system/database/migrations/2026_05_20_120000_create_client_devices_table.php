<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-device push registry for the mobile client app. A client can have
 * many devices (iPad, phone, work phone) and each holds a single FCM/APNs
 * token. We never store one row per (client, device) — we key on token
 * because (a) the same physical device can rotate tokens and (b) tokens
 * are what we actually need to fan out push payloads.
 *
 * revoked_at is set on logout-from-this-device or when FCM tells us the
 * token is no longer valid; we keep the row for audit instead of hard-
 * deleting.
 */
return new class extends Migration {
    public function up(): void
    {
        // Idempotent: a development sandbox already had this table at the
        // canonical shape. Skip if it's present so the migration runs cleanly
        // on prod and on dev boxes that pre-created it manually.
        if (Schema::hasTable('client_devices')) {
            return;
        }
        Schema::create('client_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->enum('platform', ['ios', 'android', 'web']);
            $table->string('token', 512);
            $table->string('app_version', 32)->nullable();
            $table->string('device_model', 128)->nullable();
            $table->string('os_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            // One active token per device; if the same token is registered
            // twice we update the existing row rather than duplicate.
            $table->unique('token', 'client_devices_token_unique');
            $table->index(['client_id', 'revoked_at'], 'client_devices_client_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_devices');
    }
};
