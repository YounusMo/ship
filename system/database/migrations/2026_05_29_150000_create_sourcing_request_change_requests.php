<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Change requests from the public proforma page. Lets a client say
     * "this looks good but please change X" before approving — gives the
     * negotiation a paper trail and avoids "they asked me on WhatsApp but
     * I forgot" situations.
     *
     * Workflow:
     *   pending   — submitted by client, admin hasn't responded yet
     *   responded — admin made changes and notified the client
     *   dismissed — admin acknowledged but declined to change
     *   superseded — a newer change request was submitted; old ones get
     *                rolled forward for history
     */
    public function up(): void
    {
        Schema::create('sourcing_request_change_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sourcing_request_id')->index();

            // Free-form comment the client typed.
            $t->text('comment');

            // Optional structured suggestions: [{item_id, qty?, unit_price_to_client?, note?}]
            // Stored as JSON because clients can suggest changes to any
            // number of items, and the data shape may evolve as we add
            // more fields they can negotiate over.
            $t->json('suggested_changes')->nullable();

            // Optional contact override — the client may want responses
            // sent to a different address than the one on file.
            $t->string('reply_to_email', 191)->nullable();

            $t->enum('status', ['pending', 'responded', 'dismissed', 'superseded'])
              ->default('pending')->index();

            $t->timestamp('responded_at')->nullable();
            $t->unsignedBigInteger('responded_by_user_id')->nullable();
            $t->text('response')->nullable();

            // Capture which share token was active when the request came
            // in, so we can correlate with the timeline if tokens rotate.
            $t->string('share_token_used', 64)->nullable();
            $t->string('client_ip', 64)->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sourcing_request_change_requests');
    }
};
