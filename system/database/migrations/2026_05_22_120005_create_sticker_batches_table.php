<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pre-printed roll of QR stickers. Stickers within a batch are issued
 * with ULIDs at generation time, BEFORE they're attached to any shipment.
 * Allocation happens at scan time by the employee app.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sticker_batches', function (Blueprint $t) {
            $t->id();
            $t->string('batch_code', 32)->unique();
            $t->unsignedInteger('quantity');
            $t->foreignId('generated_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $t->timestamp('generated_at')->useCurrent();
            $t->string('pdf_path', 500)->nullable();
            $t->string('notes', 1000)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sticker_batches');
    }
};
