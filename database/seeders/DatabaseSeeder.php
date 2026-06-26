<?php

namespace Database\Seeders;

use App\Models\{User, Category, Article, Setting};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /* ── Admin User ─────────────────────────────────── */
        $adminPassword = 'ADMIN@123adt';
        if (blank($adminPassword)) {
            throw new \RuntimeException(
                'ADMIN_PASSWORD must be set in the environment before seeding. '
                . 'Refusing to seed an admin account with a default password.'
            );
        }

        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@adtsports.com')],
            [
                'name'     => 'Aditya Pandit',
                'password' => Hash::make($adminPassword),
                'role'     => 'admin',
            ]
        );

        /* ── Categories ─────────────────────────────────── */
        $cats = [
            ['Match Updates',  'match-updates',  '#D4420A'],
            ['Player Stories', 'player-stories', '#16803C'],
            ['League News',    'league-news',    '#7C3AED'],
            ['Analysis',       'analysis',       '#B45309'],
            ['Grassroots',     'grassroots',     '#0891B2'],
            ['International',  'international',  '#16803C'],
            ['Originals',      'originals',      '#9333EA'],
            ['TSR Analytics',  'tsr-analytics',  '#D4420A'],
        ];
        foreach ($cats as [$name, $slug, $color]) {
            Category::firstOrCreate(['slug' => $slug], compact('name', 'slug', 'color'));
        }

        $matchCat    = Category::where('slug','match-updates')->first();
        $playerCat   = Category::where('slug','player-stories')->first();
        $globalCat   = Category::where('slug','international')->first();
        $analysisCat = Category::where('slug','analysis')->first();
        $originCat   = Category::where('slug','originals')->first();
        $tsrCat      = Category::where('slug','tsr-analytics')->first();

        // Refresh category counts
        Category::all()->each(fn($c) => $c->refreshCount());

        /* ── Default Settings ────────────────────────────── */
        $settings = [
            ['key'=>'site_name',        'value'=>'ADT Sports',                                  'group'=>'general'],
            ['key'=>'site_tagline',     'value'=>"India's #1 Kabaddi Media Platform",          'group'=>'general'],
            ['key'=>'site_email',       'value'=>'aditya03091995@gmail.com',                    'group'=>'general'],
            ['key'=>'site_phone',       'value'=>'+91 9979269732',                              'group'=>'general'],
            ['key'=>'site_whatsapp',    'value'=>'919979269732',                                'group'=>'general'],
            ['key'=>'site_address',     'value'=>'Surat, Gujarat, India',                    'group'=>'general'],
            ['key'=>'site_description', 'value'=>"India's #1 Kabaddi media platform.",          'group'=>'general'],
            ['key'=>'breaking_ticker',  'value'=>'PKL Season 11 Final: Jaipur Pink Panthers 42–37 Patna Pirates | Pardeep Narwal crosses 1,500 career raid points | Kabaddi World Cup 2025 — India squad announced', 'group'=>'general'],
            ['key'=>'footer_tagline',   'value'=>'ADT Sports is not covering Kabaddi. It is building its future.', 'group'=>'appearance'],
            ['key'=>'articles_per_page','value'=>'10',                                          'group'=>'general'],
            ['key'=>'facebook_url',     'value'=>'',                                            'group'=>'social'],
            ['key'=>'instagram_url',    'value'=>'',                                            'group'=>'social'],
            ['key'=>'youtube_url',      'value'=>'',                                            'group'=>'social'],
            ['key'=>'twitter_url',      'value'=>'',                                            'group'=>'social'],
        ];
        foreach ($settings as $s) {
            Setting::firstOrCreate(['key' => $s['key']], $s);
        }

        $this->command->info('✅ ADT Sports seeded!');
        $this->command->info('   Admin login: ' . env('ADMIN_EMAIL', 'admin@adtsports.com'));
        $this->command->info('   Password: (as set in ADMIN_PASSWORD)');
    }
}
