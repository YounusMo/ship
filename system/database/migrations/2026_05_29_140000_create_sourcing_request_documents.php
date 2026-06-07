<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Proforma-level document attachments. Separate from item photos so
     * the operator can upload signed contracts, certificates of origin,
     * packing lists, customs paperwork, etc. that aren't tied to a
     * specific line item.
     *
     * Visibility tag controls whether the client sees the doc on the
     * public share page or it's internal-only.
     */
    public function up(): void
    {
        Schema::create('sourcing_request_documents', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sourcing_request_id')->index();
            $t->string('path', 255);                  // storage/app/public/proforma/docs/...
            $t->string('original_name', 191)->nullable();
            $t->string('mime', 64)->nullable();
            $t->unsignedInteger('size_bytes')->nullable();
            $t->string('label', 191)->nullable();     // operator-supplied caption
            $t->enum('visibility', ['internal', 'client_visible'])->default('internal');
            $t->unsignedBigInteger('uploaded_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sourcing_request_documents');
    }
};
