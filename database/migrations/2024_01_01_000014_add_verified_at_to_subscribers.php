<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            // Double opt-in: marketing/commenting is gated until the address is
            // confirmed by clicking the emailed link.
            $t->timestamp('verified_at')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->dropColumn('verified_at');
        });
    }
};
