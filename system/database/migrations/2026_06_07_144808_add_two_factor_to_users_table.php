<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the two TOTP columns for admin 2FA. Both nullable — staff
 * without a secret are not yet enrolled, and a non-null
 * two_factor_confirmed_at is the signal that enrollment finished.
 *
 * The secret column is plain text (Base32 TOTP secret). Encrypting at
 * rest is worth doing later if/when we move to a vault setup; for now
 * the file-level .env protection is the baseline.
 *
 * @see docs/GAPS.md gap #6
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at']);
        });
    }
};
