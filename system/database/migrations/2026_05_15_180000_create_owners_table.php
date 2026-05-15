<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('owners', function (Blueprint $t) {
            $t->id();
            $t->string('name', 191);
            $t->string('name_en', 191)->nullable();
            $t->decimal('share_percentage', 6, 3)->default(0);
            $t->string('national_id', 64)->nullable();
            $t->string('phone', 64)->nullable();
            $t->string('email', 191)->nullable();
            $t->boolean('active')->default(true);
            $t->boolean('deleted')->default(false);
            $t->string('notes', 500)->nullable();
            $t->timestamps();
        });

        // Link an owner to a treasury transaction when the purpose is owner_drawing/salary/loan.
        Schema::table('branches_transactions', function (Blueprint $t) {
            $t->unsignedBigInteger('owner_id')->nullable()->after('purpose')->index();
        });
    }

    public function down(): void
    {
        Schema::table('branches_transactions', function (Blueprint $t) {
            $t->dropColumn('owner_id');
        });
        Schema::dropIfExists('owners');
    }
};
