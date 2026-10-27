<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('team_matches', function (Blueprint $t) {
            // Clock state is stored as "accumulated seconds + when the current
            // run started" rather than a ticking counter, so the elapsed time
            // stays correct across page reloads, server restarts and multiple
            // viewers. NULL timer_started_at means the clock is paused.
            $t->dateTime('timer_started_at')->nullable()->after('team_2_score');
            $t->unsignedInteger('timer_elapsed_seconds')->default(0)->after('timer_started_at');
            $t->string('period', 30)->nullable()->after('timer_elapsed_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('team_matches', function (Blueprint $t) {
            $t->dropColumn(['timer_started_at', 'timer_elapsed_seconds', 'period']);
        });
    }
};
