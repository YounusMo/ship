<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->date('entry_date')->index();
            $t->timestamp('posted_at')->useCurrent()->index();
            $t->unsignedBigInteger('posted_by_user_id')->nullable();
            $t->string('posted_by_user_name', 191)->nullable();

            // What real-world operation this entry represents (for human
            // readability — the canonical "what" lives in journal_lines).
            $t->string('kind', 64)->index();
            $t->string('description', 500)->nullable();

            // Pointer back to the source ledger row that triggered this entry.
            $t->string('source_table', 64)->nullable()->index();
            $t->unsignedBigInteger('source_id')->nullable()->index();
            $t->string('transaction_number', 191)->nullable()->index();

            $t->unsignedBigInteger('branch_id')->nullable()->index();

            // Reversal pointers — append-only ledger. A "correction" is a
            // reverse entry with reverses_entry_id set, plus the original is
            // updated to point back via reversed_by_entry_id.
            $t->unsignedBigInteger('reverses_entry_id')->nullable()->index();
            $t->unsignedBigInteger('reversed_by_entry_id')->nullable()->index();

            $t->enum('status', ['open', 'reversed'])->default('open')->index();
            $t->timestamps();
        });

        Schema::create('journal_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('entry_id')->index();
            $t->smallInteger('line_no');

            $t->unsignedBigInteger('account_id')->index();
            $t->string('account_code', 16);   // snapshot for reporting
            $t->string('account_name', 191);  // snapshot

            $t->decimal('dr', 18, 4)->default(0);
            $t->decimal('cr', 18, 4)->default(0);
            $t->string('currency', 8)->index();

            $t->string('description', 500)->nullable();

            // Counterparty tagging — lets reports like "AR by client" derive
            // straight from journal_lines without touching the source ledger.
            $t->string('counterparty_type', 32)->nullable()->index();
            $t->unsignedBigInteger('counterparty_id')->nullable()->index();

            $t->unsignedBigInteger('branch_id')->nullable()->index();

            $t->timestamps();

            $t->unique(['entry_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
    }
};
