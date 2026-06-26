<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Trending lists run `published()->orderByDesc('views')->limit(N)` on
        // every public page. A (status, views) composite lets MySQL satisfy the
        // filter + sort from the index instead of a filesort.
        Schema::table('articles', function (Blueprint $table) {
            $table->index(['status', 'views'], 'articles_status_views_idx');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex('articles_status_views_idx');
        });
    }
};
