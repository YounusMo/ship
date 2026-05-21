<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-device FCM/APNs registry for the EMPLOYEE app. Mirrors the existing
 * client_devices table shape (token-keyed, revoked_at for soft logout)
 * but FKs to users instead of clients.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->enum('platform', ['ios', 'android', 'web']);
            $t->string('token', 512);
            $t->string('app_version', 32)->nullable();
            $t->string('device_model', 128)->nullable();
            $t->string('os_version', 32)->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();

            $t->unique('token', 'employee_devices_token_unique');
            $t->index(['user_id', 'revoked_at'], 'employee_devices_user_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_devices');
    }
};
