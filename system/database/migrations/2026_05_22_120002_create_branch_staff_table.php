<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps a staff User to one or more Branches with a role. Sanctum employee
 * tokens carry "branch:N" abilities for the rows in this table, which
 * EnforceBranchScope middleware uses to gate scan actions.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('branch_staff', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('tracking_branches')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->enum('role', ['MANAGER', 'RECEIVER', 'COURIER', 'AUDITOR'])->default('RECEIVER');
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique(['branch_id', 'user_id'], 'branch_staff_branch_user_unique');
            $t->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_staff');
    }
};
