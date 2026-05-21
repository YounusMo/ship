<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical ShipFlow location in Libya. A tracking_branch is HUB (sea/air
 * port: Tripoli, Misrata, Benghazi) or SPOKE (inland city). The role
 * drives what kinds of internal tracking events a branch is allowed to
 * record.
 *
 * Named `tracking_branches` to avoid colliding with the legacy `branches`
 * table (CodeIgniter-era, TEXT columns, used by branchesController for
 * per-branch balance accounting). A future migration can consolidate the
 * two; for now keep them separate so the legacy surface stays intact.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tracking_branches', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 191);
            $t->string('name_en', 191)->nullable();
            $t->enum('role', ['HUB', 'SPOKE', 'ADMIN'])->default('SPOKE')->index();
            $t->string('country', 64)->default('LY');
            $t->string('city', 128);
            $t->string('address', 500)->nullable();
            $t->decimal('latitude', 10, 6)->nullable();
            $t->decimal('longitude', 10, 6)->nullable();
            $t->string('phone', 64)->nullable();
            $t->boolean('is_active')->default(true)->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_branches');
    }
};
