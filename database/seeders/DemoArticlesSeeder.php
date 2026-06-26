<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds a batch of realistic, Kabaddi-themed published articles so pagination
 * (and the home feed / feature strip / must-read sections) can be exercised.
 * Safe to run repeatedly — it skips any slug that already exists.
 *
 *   php artisan db:seed --class=DemoArticlesSeeder
 */
class DemoArticlesSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('role', 'admin')->orderBy('id')->first()
            ?? User::orderBy('id')->first();
        if (! $author) {
            $this->command->warn('No user found to author the demo articles.');
            return;
        }

        $catId = Category::pluck('id', 'slug'); // slug => id

        $headlines = [
            'match-updates' => [
                'Jaipur Pink Panthers Edge Patna Pirates in a Last-Second Super Tackle',
                'U Mumba Storm Past Bengaluru Bulls With a Ruthless Second-Half All-Out',
                'Telugu Titans Hold Off Dabang Delhi in a Do-or-Die Thriller',
                'Puneri Paltan Clinch a Tense One-Point Win Over Bengal Warriors',
            ],
            'player-stories' => [
                'From a Mud Pit in Haryana to PKL Stardom: The Arjun Deshwal Story',
                'Pawan Sehrawat on Rebuilding His Game After a Career-Threatening Injury',
                'Maninder Singh: The Quiet Architect of Bengal Warriors\' Resurgence',
                'How Fazel Atrachali Became the Most Feared Corner Defender in the League',
            ],
            'league-news' => [
                'PKL Announces an Expanded 14-Team Format for the Upcoming Season',
                'Auction Day: The Biggest Buys and Steals of the PKL Player Draft',
                'League Introduces a New Review System to Cut Down Refereeing Errors',
                'Season Schedule Revealed: Marquee Clashes and Double-Header Weekends',
            ],
            'analysis' => [
                'Why the Super Tackle Has Become Kabaddi\'s Most Decisive Weapon',
                'Breaking Down the Art of the Do-or-Die Raid',
                'The Numbers Behind Defensive Dominance: A Tactical Deep Dive',
                'Has the Bonus Line Changed the Way Teams Build Their Raiders?',
            ],
            'grassroots' => [
                'Inside the Village Akhadas Producing India\'s Next Kabaddi Stars',
                'How School Tournaments Are Fueling a Kabaddi Boom in Rural India',
                'Women\'s Kabaddi Finds a New Home in Maharashtra\'s District Leagues',
                'The Coaches Quietly Building Kabaddi\'s Grassroots Pipeline',
            ],
            'international' => [
                'Iran\'s Rise: How the Persian Powerhouse Is Challenging India\'s Reign',
                'Kabaddi\'s Global Push: New Federations Launch Across Three Continents',
                'Bangladesh and South Korea Headline an Expanding Asian Circuit',
                'Inside the Plan to Get Kabaddi Into the Olympic Conversation',
            ],
            'originals' => [
                'A Day in the Life of a Pro Kabaddi Raider',
                'The Untold Story of Kabaddi\'s First Million-Rupee Contract',
                'Mat to Mainstream: How Kabaddi Conquered Indian Living Rooms',
                'Oral History: The Match That Changed Kabaddi Forever',
            ],
            'tsr-analytics' => [
                'TSR Index: Ranking the League\'s Most Efficient Raiders This Season',
                'Defensive Success Rate: The Metric That Separates Good From Elite',
                'Heat Maps Reveal Where the League\'s Best Raiders Strike',
                'Crunching the Clutch: Who Delivers in Do-or-Die Situations?',
            ],
        ];

        $gradients = [
            'linear-gradient(145deg,#140E0A,#221808)',
            'linear-gradient(145deg,#0A1420,#101C2A)',
            'linear-gradient(145deg,#0A1A0E,#102016)',
            'linear-gradient(145deg,#1A100A,#221608)',
            'linear-gradient(145deg,#12082A,#1A1030)',
            'linear-gradient(145deg,#0A0A1A,#101020)',
        ];

        $i = 0;
        $created = 0;

        foreach ($headlines as $slug => $titles) {
            foreach ($titles as $title) {
                $articleSlug = Str::slug($title);
                if (Article::withTrashed()->where('slug', $articleSlug)->exists()) {
                    continue; // idempotent — don't duplicate on re-run
                }

                $body = $this->body($title);

                Article::create([
                    'title'        => $title,
                    'slug'         => $articleSlug,
                    'excerpt'      => $this->excerpt($title),
                    'body'         => $body,
                    'author_id'    => $author->id,
                    'category_id'  => $catId[$slug] ?? null,
                    'cover_image'  => null, // no cover → shows the category icon placeholder
                    'cover_emoji'  => '📰',
                    'cover_bg'     => $gradients[$i % count($gradients)],
                    'status'       => 'published',
                    'featured'     => $i % 11 === 0,
                    'breaking'     => $i % 17 === 0,
                    'read_time'    => Article::calculateReadTime($body),
                    'published_at' => now()->subDays($i)->subHours($i % 12),
                ])->syncTagsFromInput(['kabaddi', 'pkl', $slug]);

                $i++;
                $created++;
            }
        }

        // Keep category badges accurate.
        Category::all()->each->refreshCount();

        $this->command->info("Seeded {$created} demo articles across " . count($headlines) . ' categories.');
    }

    private function excerpt(string $title): string
    {
        return 'A closer look at ' . Str::lower(Str::limit($title, 60, '')) .
            ' — the moments, the numbers, and what it means for the season ahead.';
    }

    private function body(string $title): string
    {
        $intro = [
            'It was the kind of contest that reminds you why Kabaddi has captured a generation of fans.',
            'Few sports compress so much drama into forty minutes the way Kabaddi does — and this was no exception.',
            'On a night when the margins were razor-thin, the difference came down to nerve, timing, and preparation.',
            'Beneath the noise of a packed arena, a quieter story of strategy and discipline was unfolding.',
        ];
        $middle = [
            'The raiders worked the bonus line patiently, baiting defenders into committing early before slipping the touch and skipping back across the midline.',
            'In defence, the corners and covers moved as a unit — a chain tackle here, an ankle hold there — turning every do-or-die raid into a coin-flip.',
            'Momentum swung on a single all-out, the kind of swing that can erase a ten-point deficit in barely two minutes of play.',
            'The coaching staff had clearly drilled the super-tackle scenario, and it showed every time the defence was a man down.',
        ];
        $close = [
            'For now, the result speaks for itself — but the deeper trends are what will shape the rest of the campaign.',
            'There is plenty here for analysts to dissect, and even more for rivals to worry about.',
            'If this is a sign of things to come, the league is in for a season to remember.',
            'The story, as ever in Kabaddi, is only just beginning.',
        ];

        $a = $intro[crc32($title) % count($intro)];
        $b = $middle[crc32($title . 'm') % count($middle)];
        $c = $close[crc32($title . 'c') % count($close)];

        return "<p>{$a} {$b}</p>"
            . '<h2>How it unfolded</h2>'
            . "<p>{$b} The opening exchanges set the tone, with both sides probing for weaknesses and refusing to take a backward step.</p>"
            . '<div class="callout">Key takeaway: in the modern game, the team that controls the do-or-die raids almost always controls the scoreboard.</div>'
            . "<p>{$c}</p>";
    }
}
