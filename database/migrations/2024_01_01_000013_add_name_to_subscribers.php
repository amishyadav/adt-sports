<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->string('name')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->dropColumn('name');
        });
    }
};
