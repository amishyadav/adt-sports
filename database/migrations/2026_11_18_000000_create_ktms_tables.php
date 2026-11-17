<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema for the KTMS section: kabaddi tournaments, squads, players, matches,
 * live scoring records and player statistics.
 *
 * Every table carries a `ktms_` prefix. Two of the names KTMS uses — `teams`
 * and `match_events` — already exist in this project for the original match
 * console, so a prefix is the only way to add this section without touching
 * what is already there. The remaining seven are prefixed too, so the whole
 * section's data is obvious at a glance and can be removed in one go.
 *
 * Status and type columns are plain strings rather than enums, matching
 * team_matches.status: SQLite cannot alter a check constraint in place, so an
 * enum here would make any later change to the allowed values painful. The
 * permitted values are enforced by Rule::in in the form requests and listed in
 * the constants on each model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ktms_tournaments', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('season')->nullable();
            $t->text('description')->nullable();
            $t->string('venue')->nullable();
            $t->string('city')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('status', 20)->default('upcoming'); // upcoming|ongoing|completed

            // Defaults a new match inherits from its tournament.
            $t->unsignedSmallInteger('half_duration_seconds')->default(1200);
            $t->unsignedSmallInteger('raid_duration_seconds')->default(30);
            $t->unsignedTinyInteger('players_per_side')->default(7);
            $t->timestamps();

            $t->index('status');
        });

        Schema::create('ktms_teams', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tournament_id')->constrained('ktms_tournaments')->cascadeOnDelete();
            $t->string('name');
            $t->string('short_name', 10)->nullable();
            $t->string('logo_path')->nullable();
            $t->string('primary_color', 7)->default('#D4420A');
            $t->string('secondary_color', 7)->default('#ffffff');
            $t->string('coach_name')->nullable();
            $t->string('city')->nullable();
            $t->timestamps();

            // Squad names only need to be unique inside their own tournament.
            $t->unique(['tournament_id', 'name']);
        });

        Schema::create('ktms_players', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained('ktms_teams')->cascadeOnDelete();
            $t->string('name');
            $t->unsignedSmallInteger('jersey_number');
            $t->string('role', 20)->default('all-rounder');   // raider|defender|all-rounder
            $t->string('position', 20)->nullable();            // left-corner … right-corner
            $t->date('date_of_birth')->nullable();
            $t->unsignedSmallInteger('height_cm')->nullable();
            $t->unsignedSmallInteger('weight_kg')->nullable();
            $t->string('nationality')->nullable();
            $t->boolean('is_captain')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            // Jersey numbers must be unique inside a squad.
            $t->unique(['team_id', 'jersey_number']);
        });

        Schema::create('ktms_matches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tournament_id')->constrained('ktms_tournaments')->cascadeOnDelete();
            $t->foreignId('home_team_id')->constrained('ktms_teams')->cascadeOnDelete();
            $t->foreignId('away_team_id')->constrained('ktms_teams')->cascadeOnDelete();
            $t->unsignedInteger('match_number')->nullable();
            $t->string('round')->nullable();
            $t->string('venue')->nullable();
            $t->dateTime('scheduled_at')->nullable();

            $t->string('status', 20)->default('scheduled'); // scheduled|live|half_time|completed

            // Scoreboard
            $t->unsignedInteger('home_score')->default(0);
            $t->unsignedInteger('away_score')->default(0);

            // Players standing on the mat for each side. This is the authoritative
            // count, because it is what the scoreboard shows; the lineup pivot is
            // kept in step so the display can also name who is out.
            $t->unsignedTinyInteger('home_players_on_court')->default(7);
            $t->unsignedTinyInteger('away_players_on_court')->default(7);
            $t->unsignedTinyInteger('players_per_side')->default(7);

            $t->unsignedTinyInteger('current_half')->default(1);
            $t->unsignedSmallInteger('half_duration_seconds')->default(1200);

            /*
             * Game clock. `game_clock_remaining_seconds` is the authoritative value
             * at the instant recorded in `game_timer_updated_at`. While the timer is
             * running the live value is (remaining - (now - updated_at)), which lets
             * any number of browsers render the same countdown without drifting.
             * The three live screens all depend on this anchor technique.
             */
            $t->unsignedSmallInteger('game_clock_remaining_seconds')->default(1200);
            $t->boolean('game_timer_running')->default(false);
            $t->timestamp('game_timer_updated_at')->nullable();

            // Raid clock, same anchor technique.
            $t->unsignedSmallInteger('raid_duration_seconds')->default(30);
            $t->unsignedSmallInteger('raid_clock_remaining_seconds')->default(30);
            $t->boolean('raid_timer_running')->default(false);
            $t->timestamp('raid_timer_updated_at')->nullable();

            $t->boolean('is_raid_active')->default(false);
            $t->foreignId('raiding_team_id')->nullable()->constrained('ktms_teams')->nullOnDelete();
            $t->unsignedInteger('raid_count')->default(0);

            $t->foreignId('winner_team_id')->nullable()->constrained('ktms_teams')->nullOnDelete();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();

            $t->index(['tournament_id', 'status']);
        });

        Schema::create('ktms_match_player', function (Blueprint $t) {
            $t->id();
            $t->foreignId('match_id')->constrained('ktms_matches')->cascadeOnDelete();
            $t->foreignId('player_id')->constrained('ktms_players')->cascadeOnDelete();
            $t->foreignId('team_id')->constrained('ktms_teams')->cascadeOnDelete();
            $t->boolean('is_starter')->default(false);
            $t->boolean('is_on_court')->default(false);
            $t->string('status', 20)->default('bench'); // on_court|out|bench
            $t->timestamps();

            $t->unique(['match_id', 'player_id']);
            $t->index(['match_id', 'team_id']);
        });

        Schema::create('ktms_raids', function (Blueprint $t) {
            $t->id();
            $t->foreignId('match_id')->constrained('ktms_matches')->cascadeOnDelete();
            $t->foreignId('raider_id')->constrained('ktms_players')->cascadeOnDelete();
            $t->foreignId('raiding_team_id')->constrained('ktms_teams')->cascadeOnDelete();
            $t->foreignId('defending_team_id')->constrained('ktms_teams')->cascadeOnDelete();

            $t->unsignedInteger('raid_number');

            // successful   -> the raider scored at least one point
            // unsuccessful -> the raider was tackled
            // empty        -> nobody scored
            $t->string('result', 20)->default('empty');

            $t->unsignedTinyInteger('touch_points')->default(0);
            $t->unsignedTinyInteger('bonus_points')->default(0);
            $t->unsignedTinyInteger('raid_points')->default(0);      // total to the raiding side
            $t->unsignedTinyInteger('defending_points')->default(0); // total to the defending side

            $t->boolean('is_bonus')->default(false);
            $t->boolean('is_super_raid')->default(false);
            $t->boolean('is_do_or_die')->default(false);

            $t->unsignedTinyInteger('half')->default(1);
            $t->unsignedSmallInteger('game_clock_at_event')->nullable();
            $t->unsignedSmallInteger('raid_duration_seconds')->nullable();
            $t->string('notes')->nullable();
            $t->timestamps();

            $t->index(['match_id', 'raid_number']);
            $t->index('raider_id');
            $t->index(['raiding_team_id', 'result']);
        });

        Schema::create('ktms_defensive_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('match_id')->constrained('ktms_matches')->cascadeOnDelete();
            // Usually part of a raid, but the operator can also log a standalone
            // defensive event, so the raid link stays optional.
            $t->foreignId('raid_id')->nullable()->constrained('ktms_raids')->cascadeOnDelete();
            $t->foreignId('defender_id')->constrained('ktms_players')->cascadeOnDelete();
            $t->foreignId('team_id')->constrained('ktms_teams')->cascadeOnDelete();

            // tackle|super_tackle|ankle_hold|thigh_hold|block|dash|chain_tackle
            $t->string('action_type', 20)->default('tackle');

            $t->boolean('is_successful')->default(false);
            $t->unsignedTinyInteger('points_awarded')->default(0);

            $t->unsignedTinyInteger('half')->default(1);
            $t->unsignedSmallInteger('game_clock_at_event')->nullable();
            $t->string('notes')->nullable();
            $t->timestamps();

            $t->index(['match_id', 'defender_id']);
            $t->index('raid_id');
        });

        Schema::create('ktms_player_statistics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('player_id')->constrained('ktms_players')->cascadeOnDelete();
            $t->foreignId('tournament_id')->constrained('ktms_tournaments')->cascadeOnDelete();

            $t->unsignedInteger('matches_played')->default(0);

            // Raiding
            $t->unsignedInteger('total_raids')->default(0);
            $t->unsignedInteger('successful_raids')->default(0);
            $t->unsignedInteger('unsuccessful_raids')->default(0);
            $t->unsignedInteger('empty_raids')->default(0);
            $t->unsignedInteger('raid_points')->default(0);
            $t->unsignedInteger('touch_points')->default(0);
            $t->unsignedInteger('bonus_points')->default(0);
            $t->unsignedInteger('super_raids')->default(0);
            $t->unsignedInteger('do_or_die_raids')->default(0);
            $t->unsignedInteger('do_or_die_conversions')->default(0);

            // Defending
            $t->unsignedInteger('total_defenses')->default(0);
            $t->unsignedInteger('successful_defenses')->default(0);
            $t->unsignedInteger('failed_defenses')->default(0);
            $t->unsignedInteger('defense_points')->default(0);
            $t->unsignedInteger('super_tackles')->default(0);

            $t->unsignedInteger('total_points')->default(0);
            $t->timestamps();

            // One aggregate row per player per tournament, so a record is scoped
            // to the competition it was earned in.
            $t->unique(['player_id', 'tournament_id']);
            $t->index(['tournament_id', 'total_points']);
        });

        Schema::create('ktms_match_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('match_id')->constrained('ktms_matches')->cascadeOnDelete();
            $t->foreignId('team_id')->nullable()->constrained('ktms_teams')->nullOnDelete();
            $t->foreignId('player_id')->nullable()->constrained('ktms_players')->nullOnDelete();
            $t->foreignId('raid_id')->nullable()->constrained('ktms_raids')->nullOnDelete();

            $t->string('event_type', 40);
            $t->string('description');
            $t->integer('points')->default(0);

            $t->unsignedTinyInteger('half')->default(1);
            $t->unsignedSmallInteger('game_clock_at_event')->nullable();

            // Snapshot of the counters before the action ran. This is what undo
            // replays, so it is the reason a correction can never leave the
            // scoreboard and the court count disagreeing.
            $t->json('payload')->nullable();
            $t->boolean('is_undone')->default(false);
            $t->timestamps();

            $t->index(['match_id', 'id']);
            $t->index('event_type');
        });
    }

    public function down(): void
    {
        // Reverse order so the foreign keys come apart cleanly.
        Schema::dropIfExists('ktms_match_events');
        Schema::dropIfExists('ktms_player_statistics');
        Schema::dropIfExists('ktms_defensive_actions');
        Schema::dropIfExists('ktms_raids');
        Schema::dropIfExists('ktms_match_player');
        Schema::dropIfExists('ktms_matches');
        Schema::dropIfExists('ktms_players');
        Schema::dropIfExists('ktms_teams');
        Schema::dropIfExists('ktms_tournaments');
    }
};
