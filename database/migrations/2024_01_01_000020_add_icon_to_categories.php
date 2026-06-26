<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $t) {
            $t->string('icon')->nullable()->after('color');
        });

        // Seed the existing categories with the icons they already render with,
        // so nothing visibly changes and the Edit form shows them pre-selected.
        $map = [
            'match-updates'  => 'fa-trophy',
            'player-stories' => 'fa-user',
            'league-news'    => 'fa-bullhorn',
            'analysis'       => 'fa-chart-line',
            'grassroots'     => 'fa-seedling',
            'international'   => 'fa-earth-asia',
            'originals'      => 'fa-star',
            'tsr-analytics'  => 'fa-chart-simple',
        ];

        foreach ($map as $slug => $icon) {
            DB::table('categories')->where('slug', $slug)->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $t) {
            $t->dropColumn('icon');
        });
    }
};
