<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('match_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_match_id')->constrained('team_matches')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $t->string('type', 30);                       // safe_raid, super_raid, all_out, point…
            $t->unsignedTinyInteger('team')->nullable();  // 1 or 2, null for match-wide events
            $t->integer('points')->default(0);
            $t->string('description')->nullable();

            // The match state immediately BEFORE this event. Undo restores this
            // blob and drops the row, which makes "Undo" exact for any action
            // rather than trying to invert each one by hand.
            $t->json('snapshot');

            $t->dateTime('created_at')->nullable();

            $t->index(['team_match_id', 'id']);           // newest-first lookups per match
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_events');
    }
};
