<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('team_matches', function (Blueprint $t) {
            // Kabaddi keeps seven players on the mat per side; the console lets
            // the operator set the survivors after each raid.
            $t->unsignedTinyInteger('players_left_1')->default(7)->after('team_1_score');
            $t->unsignedTinyInteger('players_left_2')->default(7)->after('team_2_score');

            // The match clock counts DOWN from a configurable half length
            // (20 minutes by default), so the duration has to be stored too.
            $t->unsignedInteger('timer_duration_seconds')->default(1200)->after('timer_elapsed_seconds');

            // The 30-second raid clock. raid_team is the side currently raiding;
            // NULL means nobody is in, so the clock shows a full raid.
            $t->unsignedTinyInteger('raid_team')->nullable()->after('period');
            $t->dateTime('raid_started_at')->nullable()->after('raid_team');
            $t->unsignedSmallInteger('raid_duration_seconds')->default(30)->after('raid_started_at');

            // Teams change ends at half time. Flipping this swaps which side of
            // the main screen and live strip each team appears on, without
            // touching team_1 / team_2 — so the scores never get mixed up.
            $t->boolean('courts_swapped')->default(false)->after('raid_duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('team_matches', function (Blueprint $t) {
            $t->dropColumn([
                'players_left_1', 'players_left_2',
                'timer_duration_seconds',
                'raid_team', 'raid_started_at', 'raid_duration_seconds',
                'courts_swapped',
            ]);
        });
    }
};
