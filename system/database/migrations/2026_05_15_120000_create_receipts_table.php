<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receipts — strictly sequential, append-only, never reused.
 *
 * Every money-touching transaction (client deposit, withdraw, transfer,
 * supplier deposit, customs broker deposit, etc.) issues exactly one
 * receipt at the moment the transaction succeeds, inside the same DB
 * transaction. If the underlying transaction rolls back, the receipt
 * row rolls back with it — the gap in `series_number` then stays as
 * evidence the receipt was never validly issued.
 *
 * `series_letter` lets you partition the sequence by year or branch
 * (e.g. "2026-A" wraps around to "2027-A"). Default 'A' if unused.
 * Tax-authority inspectors prefer a continuous numeric trail, so
 * defaulting to a single letter keeps the sequence simple.
 *
 * Voiding: never delete a row. Mark `voided=true` with the user who
 * voided and the reason. The number stays burnt — you can't reissue
 * it as a fresh receipt.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $t) {
            $t->id();
            $t->string('series_letter', 4)->default('A');
            $t->unsignedBigInteger('series_number');

            // Link to the source transaction
            $t->string('source_table', 64);         // 'clients_transactions' / 'suppliers_transactions' / etc.
            $t->unsignedBigInteger('source_id');    // row id in source table
            $t->string('transaction_number', 191)->nullable()->index();
            $t->unsignedBigInteger('auto_id')->nullable()->index();

            // Cached display fields (the source row may be edited later — receipt is a frozen snapshot)
            $t->string('kind', 32);                 // 'deposit' / 'withdraw' / 'transfer' / 'commission' / 'supplier_deposit' / 'customs_deposit'
            $t->string('currency', 8)->nullable();
            $t->decimal('amount', 20, 4)->nullable();
            $t->string('counterparty_type', 16)->nullable();  // 'client' / 'supplier' / 'customs_broker'
            $t->unsignedBigInteger('counterparty_id')->nullable();
            $t->string('counterparty_label', 191)->nullable();
            $t->string('counterparty_code', 64)->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->string('purpose', 64)->nullable();
            $t->text('notes')->nullable();

            // Who issued
            $t->unsignedBigInteger('issued_by_user_id')->nullable();
            $t->string('issued_by_user_name', 191)->nullable();
            $t->dateTime('issued_at');

            // Void
            $t->boolean('voided')->default(false);
            $t->unsignedBigInteger('voided_by_user_id')->nullable();
            $t->dateTime('voided_at')->nullable();
            $t->string('void_reason', 191)->nullable();

            $t->unique(['series_letter', 'series_number'], 'receipts_series_unique');
            $t->index(['source_table', 'source_id']);
            $t->index(['counterparty_type', 'counterparty_id']);
            $t->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
