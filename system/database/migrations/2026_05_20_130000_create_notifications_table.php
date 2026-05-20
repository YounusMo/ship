<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel-standard notifications table. Used by Illuminate\Notifications\Notifiable
 * to persist the in-app notification feed (transactions, shipment status,
 * receipts). Stored as JSON in `data` so each notification class can
 * shape its own payload — the app reads `type` + `data` to render.
 *
 * Push delivery is a separate channel (see Notifications/Channels/FcmChannel).
 * This table is the source of truth for "what to show in the in-app list".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Unread feed for a given notifiable, newest first — the
            // dominant query pattern from the mobile app.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_unread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
