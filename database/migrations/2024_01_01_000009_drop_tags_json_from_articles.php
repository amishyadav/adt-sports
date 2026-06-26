<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Tags now live in the normalized tags + article_tag tables
        // (backfilled by the previous migration).
        if (Schema::hasColumn('articles', 'tags')) {
            Schema::table('articles', function (Blueprint $t) {
                $t->dropColumn('tags');
            });
        }
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $t) {
            $t->json('tags')->nullable();
        });
    }
};
