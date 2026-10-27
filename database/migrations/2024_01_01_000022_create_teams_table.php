<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('short_name', 10)->nullable();  // scoreboard abbreviation, e.g. "IND"
            $t->string('logo')->nullable();            // public path, e.g. /uploads/teams/<uuid>.png
            $t->boolean('status')->default(true);      // active / inactive toggle
            $t->timestamps();

            $t->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
