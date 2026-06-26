<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('article_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->text('excerpt')->nullable();
            $t->longText('body')->nullable();
            $t->timestamp('created_at')->nullable();

            $t->index(['article_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_revisions');
    }
};
