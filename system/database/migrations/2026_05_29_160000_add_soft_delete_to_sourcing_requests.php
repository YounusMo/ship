<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Soft-delete column + matching index so trashed proformas are easy
     * to filter without dropping the data. The existing 'canceled' state
     * stays as the *business* outcome ("we shut the deal down"); trashed
     * is the *housekeeping* state ("operator hid this from the list").
     * They're orthogonal.
     */
    public function up(): void
    {
        Schema::table('sourcing_requests', function (Blueprint $t) {
            $t->softDeletes();   // adds deleted_at timestamp
            $t->index('deleted_at', 'sourcing_requests_deleted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sourcing_requests', function (Blueprint $t) {
            $t->dropIndex('sourcing_requests_deleted_at_idx');
            $t->dropSoftDeletes();
        });
    }
};
