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
        $adminPassword = env('ADMIN_PASSWORD');
        if (blank($adminPassword)) {
            throw new \RuntimeException(
                'ADMIN_PASSWORD must be set in the environment before seeding. '
                . 'Refusing to seed an admin account with a default password.'
            );
        }

        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@adtsports.com')],
            [
                'name'     => 'Aditya Trivedi',
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

        /* ── Sample Articles ─────────────────────────────── */
        $articles = [
            [
                'title'       => 'PKL Season 11 Finals: Jaipur Pink Panthers Seal Historic Championship Against Patna Pirates',
                'excerpt'     => "A stunning defensive performance combined with Arjun Deshwal's 15-point haul sealed a historic championship in front of a roaring home crowd.",
                'cover_emoji' => '🤸',
                'cover_bg'    => 'linear-gradient(145deg,#140E0A,#221808)',
                'category_id' => $matchCat?->id,
                'status'      => 'published',
                'featured'    => true,
                'breaking'    => false,
                'tags'        => ['PKL','Jaipur','Finals','Kabaddi'],
                'body'        => "<p>The Sawai Mansingh Stadium fell silent for just a moment before erupting — Jaipur Pink Panthers had done it again. In what many are calling the greatest PKL final of the modern era, the Panthers defeated Patna Pirates 42–37 in a match that swung four times in the final ten minutes.</p><h2>Patna's Early Dominance</h2><p>Patna came out aggressive. Their defensive unit, anchored by veteran Reza Mirbagheri, shut down Jaipur's raid attempts in the opening eight minutes to build a 14–8 lead.</p><blockquote>\"We knew they'd come hard. We let them have the first ten minutes. Then we took everything back.\" — Arjun Deshwal, post-match</blockquote><h2>Deshwal's Historic Performance</h2><p>Arjun Deshwal's 15-point raid haul was the difference. Each touch felt surgical — probing the left corner, retreating before the cover could lock, then exploding through the right channel.</p><div class=\"callout\">📊 <strong>TSR Stat:</strong> Deshwal's raid success rate in the final was 78.9% — the highest ever recorded in a PKL final under ADT's TSR tracking system.</div><h2>The Final Ten Minutes</h2><p>An all-out in the 32nd minute gave Patna a six-point swing. Jaipur responded with a super raid three minutes later. The lead changed hands three times before a perfectly-timed ankle hold in the 38th minute sealed the match for the Panthers.</p>",
                'published_at'=> now()->subDays(2),
            ],
            [
                'title'       => 'Pardeep Narwal — The Dubki King Rewrites History With His 1,500th Career Raid Point',
                'excerpt'     => 'In a quiet moment during a league match in Pune, the greatest raider in Kabaddi history crossed a milestone that may never be matched.',
                'cover_emoji' => '🏆',
                'cover_bg'    => 'linear-gradient(145deg,#0A1420,#101C2A)',
                'category_id' => $playerCat?->id,
                'status'      => 'published',
                'featured'    => false,
                'tags'        => ['Pardeep Narwal','Record','PKL'],
                'body'        => "<p>There was no fanfare. No dramatic slow-motion replay. Just Pardeep Narwal stepping back across the baulk line, and a digital scoreboard ticking from 1,499 to 1,500. The greatest raider in Kabaddi history had done it.</p><h2>Numbers That Define an Era</h2><p>Nine PKL seasons, 150+ matches, and now this historic milestone belongs to the Dubki King.</p><blockquote>\"This number means nothing without my defenders, my coaches, my family. Kabaddi is never one man.\" — Pardeep Narwal</blockquote><h2>The Dubki — A Move Built for Legacy</h2><p>His signature ankle-level escape is now taught in coaching academies across India and Bangladesh. ADT's TSR data shows a 71.3% career success rate — remarkable given defenders have had nine seasons to study and counter it.</p>",
                'published_at'=> now()->subDays(4),
            ],
            [
                'title'       => 'World Kabaddi Federation Announces 2025 World Cup Format — India, Iran & Bangladesh Are Favourites',
                'excerpt'     => 'The WKF has unveiled a 16-nation tournament with a new bonus point system designed to incentivise high-scoring, attacking Kabaddi.',
                'cover_emoji' => '🌐',
                'cover_bg'    => 'linear-gradient(145deg,#0A1A0E,#102016)',
                'category_id' => $globalCat?->id,
                'status'      => 'published',
                'featured'    => false,
                'tags'        => ['World Cup','WKF','India','International'],
                'body'        => "<p>The World Kabaddi Federation has confirmed the 2025 World Cup will feature 16 nations in a round-robin to knockout format, with a new bonus point structure that rewards super raids.</p><h2>New Format Explained</h2><p>Teams scoring a super raid — taking three or more opposition players off the mat in a single touch — will now receive a bonus competition point. The WKF believes this rewards the aggressive, high-skill raiding that global audiences respond to.</p><blockquote>\"We want the World Cup to show the world what Kabaddi at its finest looks like.\" — WKF Secretary General</blockquote>",
                'published_at'=> now()->subDays(5),
            ],
            [
                'title'       => 'TSR Data Reveals: The Top 5 Defenders by Tackle Success Rate in PKL Season 11',
                'excerpt'     => "ADT's proprietary TSR system processed 12,447 defensive actions from the 2025 season to reveal who truly leads in defensive quality.",
                'cover_emoji' => '📊',
                'cover_bg'    => 'linear-gradient(145deg,#1A100A,#221608)',
                'category_id' => $tsrCat?->id,
                'status'      => 'published',
                'featured'    => false,
                'tags'        => ['TSR','Analytics','Defence','PKL'],
                'body'        => "<p>ADT Sports' TSR system logged 12,447 individual tackle attempts across PKL Season 11. After normalising for raid quality, defensive positioning, and match context, five defenders stand decisively apart.</p><div class=\"callout\">1. <strong>Reza Mirbagheri</strong> (Patna Pirates) — 73.1%<br>2. <strong>Saurabh Nandal</strong> (Delhi Dabang) — 71.8%<br>3. <strong>Mohammadreza Shadloui</strong> (Bengaluru Bulls) — 69.4%<br>4. <strong>Pawan Kumar</strong> (UP Yoddhas) — 67.2%<br>5. <strong>Ankush</strong> (Haryana Steelers) — 65.9%</div><h2>How TSR Scores a Tackle</h2><p>TSR tackle success isn't a simple binary. The system weights each attempt by the raider's escape probability based on position, momentum, and active defender count.</p>",
                'published_at'=> now()->subDays(6),
            ],
            [
                'title'       => 'From Village Mud Mats to PKL Arenas: Ravi Dhankar\'s Journey in His Own Words',
                'excerpt'     => "The 24-year-old from Ateli village, Haryana, tells ADT Sports about years of 4 AM training sessions, family sacrifice, and the moment everything changed.",
                'cover_emoji' => '📖',
                'cover_bg'    => 'linear-gradient(145deg,#141206,#1E1A0A)',
                'category_id' => $originCat?->id,
                'status'      => 'published',
                'featured'    => false,
                'tags'        => ['Originals','Player Journey','Grassroots'],
                'body'        => "<p><em>Ravi Dhankar grew up on a mud Kabaddi court his father built behind their family home in Ateli village, Haryana. At 24, he played his first PKL match in front of 12,000 people. This is his story.</em></p><h2>\"The mat was everything\"</h2><p>My father never played professionally. He built us that court — 13 by 10 metres of packed mud, chalk lines that washed away every monsoon. But it was ours.</p><blockquote>\"I used to wake up at 4 AM because that was the coolest part of the day in summer. My father would already be there, doing footwork drills. I just copied him.\"</blockquote><h2>\"Nobody from our village had played PKL\"</h2><p>When district coaches came to scout, I'd been training for six years. They told my father I had good instincts but my ankle mobility needed work. I spent the next eight months on nothing but ankle strengthening — twice a day, every day.</p>",
                'published_at'=> now()->subDays(9),
            ],
            [
                'title'       => 'Why Super Tackles Are Reshaping PKL Strategy: A Deep Dive Into Season 11',
                'excerpt'     => 'Super tackle frequency rose 34% year-on-year. TSR data reveals the unexpected game theory driving this tactical evolution.',
                'cover_emoji' => '🎯',
                'cover_bg'    => 'linear-gradient(145deg,#180A18,#221030)',
                'category_id' => $analysisCat?->id,
                'status'      => 'published',
                'featured'    => false,
                'tags'        => ['Analysis','Super Tackle','Tactics','PKL'],
                'body'        => "<p>Super tackles — where three or fewer players on the mat successfully stop a raider — have always been Kabaddi's highest-drama moment. In PKL Season 11, they occurred 34% more than the previous year.</p><div class=\"callout\">Season 10: 147 super tackles / 132 matches (1.11/match)<br>Season 11: 197 super tackles / 132 matches (1.49/match)</div><h2>The Game Theory at Play</h2><p>Coaches have identified that an outnumbered defensive unit committing to a surprise tackle attempt — rather than passively conceding — creates a psychological shift that can swing momentum even when the tackle fails.</p>",
                'published_at'=> now()->subDays(11),
            ],
        ];

        foreach ($articles as $data) {
            $slug = Article::generateSlug($data['title']);
            $rt   = Article::calculateReadTime($data['body']);
            $tags = $data['tags'] ?? [];
            unset($data['tags']);

            $article = Article::firstOrCreate(['slug' => $slug], array_merge($data, [
                'author_id' => $admin->id,
                'slug'      => $slug,
                'read_time' => $rt,
            ]));

            $article->syncTagsFromInput($tags);
        }

        // Refresh category counts
        Category::all()->each(fn($c) => $c->refreshCount());

        /* ── Default Settings ────────────────────────────── */
        $settings = [
            ['key'=>'site_name',        'value'=>'ADT Sports',                                  'group'=>'general'],
            ['key'=>'site_tagline',     'value'=>"India's #1 Kabaddi Media Platform",          'group'=>'general'],
            ['key'=>'site_email',       'value'=>'aditya03091995@gmail.com',                    'group'=>'general'],
            ['key'=>'site_phone',       'value'=>'+91 9979269732',                              'group'=>'general'],
            ['key'=>'site_whatsapp',    'value'=>'919979269732',                                'group'=>'general'],
            ['key'=>'site_address',     'value'=>'Jaipur, Rajasthan, India',                    'group'=>'general'],
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
