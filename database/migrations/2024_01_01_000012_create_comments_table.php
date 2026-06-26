<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->string('author_name');
            $t->string('author_email');
            $t->text('body');
            $t->boolean('approved')->default(false);
            $t->string('ip', 45)->nullable();
            $t->timestamps();

            // Hot path: list approved comments for an article, newest first.
            $t->index(['article_id', 'approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
