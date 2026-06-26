<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $t) {
            $t->unsignedBigInteger('likes')->default(0)->after('views');
        });

        // One row per (article, browser-fingerprint) so a visitor can like an
        // article at most once. The unique key both dedupes and lets us toggle.
        Schema::create('article_likes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->string('fingerprint', 64);
            $t->timestamp('created_at')->nullable();
            $t->unique(['article_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_likes');
        Schema::table('articles', function (Blueprint $t) {
            $t->dropColumn('likes');
        });
    }
};
