<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $t) {
            $t->id();
            $t->string('email')->unique();
            $t->string('source')->nullable(); // where they subscribed from (home, article, …)
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
