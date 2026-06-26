<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // FULLTEXT is a MySQL feature; SQLite (used in tests) falls back to LIKE.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->fullText(['title', 'excerpt', 'body'], 'articles_fulltext');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->dropFullText('articles_fulltext');
        });
    }
};
