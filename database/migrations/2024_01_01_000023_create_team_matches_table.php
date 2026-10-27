<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_matches', function (Blueprint $t) {
            $t->id();

            // Column names mirror the admin form labels ("Team 1" / "Team 2").
            // Deleting a team cascades its fixtures away; TeamController also
            // guards the delete so this only fires on direct DB/cascade work.
            $t->foreignId('team_1')->constrained('teams')->cascadeOnDelete();
            $t->foreignId('team_2')->constrained('teams')->cascadeOnDelete();

            // Plain string, not enum: SQLite has to rebuild the whole table to
            // alter an enum check constraint, and the match lifecycle is the
            // kind of list that grows. Allowed values live on TeamMatch::STATUSES.
            $t->string('status', 20)->default('pending');

            $t->unsignedInteger('team_1_score')->default(0);
            $t->unsignedInteger('team_2_score')->default(0);
            $t->dateTime('scheduled_at')->nullable();
            $t->string('venue')->nullable();
            $t->timestamps();

            $t->index('status');
            $t->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_matches');
    }
};
