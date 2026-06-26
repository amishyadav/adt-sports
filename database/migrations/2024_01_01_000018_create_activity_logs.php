<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action')->index();
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('description')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->nullable()->index();

            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
