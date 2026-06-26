<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Transient buffer: page views accumulate here (cheap, narrow, no indexes
        // beyond the PK and no updated_at) and are flushed into articles.views on
        // a schedule, keeping the hot articles row off the request write path.
        Schema::create('article_view_buffer', function (Blueprint $t) {
            $t->unsignedBigInteger('article_id')->primary();
            $t->unsignedBigInteger('count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_view_buffer');
    }
};
