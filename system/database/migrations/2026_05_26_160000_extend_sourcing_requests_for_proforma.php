<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Promote sourcing_requests from "a finder's-fee tracker" into a real
     * proforma-invoice document. The new columns describe how the offer is
     * presented to the client (currency, commission mode, terms), how it's
     * sent and approved (share_token, approved_via), and what payment plan
     * the client agreed to. The payment schedule rows themselves live in
     * sourcing_request_payments.
     *
     * Keeps existing columns intact — earlier sourcing requests still work,
     * they just have NULLs for the new fields and default behaviour.
     */
    public function up(): void
    {
        Schema::table('sourcing_requests', function (Blueprint $t) {
            // What currency the client sees on the proforma totals. Items
            // can still be denominated in their own (e.g. supplier-side CNY)
            // — we convert with fx_rate_snapshot frozen at send time so the
            // figures don't drift if FX changes later.
            $t->string('display_currency', 8)->nullable()->after('currency');
            $t->json('fx_rate_snapshot')->nullable()->after('display_currency');
            $t->date('fx_frozen_on')->nullable()->after('fx_rate_snapshot');

            // How the operator's commission is shown to the client:
            //   visible_separate — line item "Commission: $X" on the PDF
            //   hidden_in_prices — markup baked into per-unit prices, no
            //                      separate line; client only sees totals
            // Internally we always track the commission as its own value so
            // P&L / margin reports work regardless of presentation mode.
            $t->enum('commission_mode', ['visible_separate', 'hidden_in_prices'])
              ->default('hidden_in_prices')
              ->after('commission_journal_entry_id');

            // Payment plan template the schedule was generated from. Edits
            // to individual schedule rows are still allowed — this is the
            // starting point, not a constraint.
            $t->enum('payment_plan', ['100', '50_50', '30_50_20', '30_30_40', 'custom'])
              ->default('100')
              ->after('commission_mode');

            // Free-form terms / notes block shown on the proforma footer.
            $t->text('terms_text')->nullable()->after('payment_plan');

            // Cached totals — recomputed on every item / commission edit so
            // reports and listings don't have to sum line items every time.
            $t->decimal('items_subtotal', 18, 4)->default(0)->after('terms_text');
            $t->decimal('proforma_total', 18, 4)->default(0)->after('items_subtotal');

            // Public share link. NULL until the proforma is sent; populated
            // with a 40-char URL-safe token the client uses to open the
            // public approval page without logging in.
            $t->string('share_token', 64)->nullable()->unique()->after('proforma_total');
            $t->timestamp('share_token_expires_at')->nullable()->after('share_token');
            $t->timestamp('sent_at')->nullable()->after('share_token_expires_at');

            // Which path approved this proforma:
            //   client_portal — the client clicked Approve on the public link
            //   on_behalf     — an internal user accepted for the client
            //                   (verbal / WhatsApp confirmation, etc.)
            //   admin_direct  — fast-path internal use, no client involvement
            $t->enum('approved_via', ['client_portal', 'on_behalf', 'admin_direct'])
              ->nullable()
              ->after('sent_at');
            $t->unsignedBigInteger('approved_by_user_id')->nullable()->after('approved_via');
            $t->timestamp('approved_at')->nullable()->after('approved_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('sourcing_requests', function (Blueprint $t) {
            $t->dropUnique(['share_token']);
            $t->dropColumn([
                'display_currency', 'fx_rate_snapshot', 'fx_frozen_on',
                'commission_mode', 'payment_plan', 'terms_text',
                'items_subtotal', 'proforma_total',
                'share_token', 'share_token_expires_at', 'sent_at',
                'approved_via', 'approved_by_user_id', 'approved_at',
            ]);
        });
    }
};
