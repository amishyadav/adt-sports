<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            // Single-use confirmation: rotated on each subscribe, cleared on confirm.
            $t->string('confirmation_token', 64)->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->dropColumn('confirmation_token');
        });
    }
};
