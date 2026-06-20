<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\{Article, Category, Setting, User};
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SeoController extends Controller
{
    /**
     * Dynamic robots.txt — allow public pages, disallow the admin panel,
     * and advertise the sitemap locations.
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /search',
            'Allow: /',
            '',
            'Sitemap: ' . url('/sitemap.xml'),
            'Sitemap: ' . url('/news-sitemap.xml'),
        ];

        return response(implode("\n", $lines) . "\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * XML sitemap listing the home page, category pages, and all
     * published articles (with cover-image entries) for discovery.
     */
    public function sitemap(): Response
    {
        $xml = Cache::remember('seo.sitemap', 3600, function () {
            $urls = [];

            $urls[] = [
                'loc'        => route('home'),
                'changefreq' => 'hourly',
                'priority'   => '1.0',
            ];

            foreach (Category::orderBy('name')->get() as $category) {
                $urls[] = [
                    'loc'        => route('category', $category->slug),
                    'lastmod'    => optional($category->updated_at)->toAtomString(),
                    'changefreq' => 'daily',
                    'priority'   => '0.7',
                ];
            }

            foreach (Article::published()->latest('published_at')->get() as $article) {
                $urls[] = [
                    'loc'        => route('article', $article->slug),
                    'lastmod'    => optional($article->updated_at ?? $article->published_at)->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority'   => '0.8',
                    'image'      => $this->absoluteUrl($article->cover_image),
                ];
            }

            // Author archive pages (only authors with published articles).
            foreach (User::whereHas('articles', fn ($q) => $q->published())->get() as $author) {
                $urls[] = [
                    'loc'        => route('author', $author->id),
                    'changefreq' => 'weekly',
                    'priority'   => '0.5',
                ];
            }

            // Tag archive pages (distinct tags across published articles).
            $tags = Article::published()->pluck('tags')
                ->flatMap(fn ($t) => is_array($t) ? $t : [])
                ->map(fn ($t) => trim((string) $t))
                ->filter()
                ->unique();
            foreach ($tags as $tag) {
                $urls[] = [
                    'loc'        => route('tag', $tag),
                    'changefreq' => 'weekly',
                    'priority'   => '0.5',
                ];
            }

            $out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
                  . ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
            foreach ($urls as $url) {
                $out .= "  <url>\n";
                $out .= '    <loc>' . e($url['loc']) . "</loc>\n";
                if (! empty($url['lastmod'])) {
                    $out .= '    <lastmod>' . e($url['lastmod']) . "</lastmod>\n";
                }
                $out .= '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
                $out .= '    <priority>' . $url['priority'] . "</priority>\n";
                if (! empty($url['image'])) {
                    $out .= "    <image:image>\n";
                    $out .= '      <image:loc>' . e($url['image']) . "</image:loc>\n";
                    $out .= "    </image:image>\n";
                }
                $out .= "  </url>\n";
            }
            $out .= '</urlset>';

            return $out;
        });

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Google News sitemap — articles published within the last 48 hours.
     * @see https://support.google.com/news/publisher-center/answer/9606710
     */
    public function newsSitemap(): Response
    {
        $xml = Cache::remember('seo.news_sitemap', 600, function () {
            $siteName = Setting::get('site_name', 'ADT Sports');

            $articles = Article::published()
                ->where('published_at', '>=', now()->subHours(48))
                ->latest('published_at')
                ->get();

            $out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
                  . ' xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
            foreach ($articles as $article) {
                $out .= "  <url>\n";
                $out .= '    <loc>' . e(route('article', $article->slug)) . "</loc>\n";
                $out .= "    <news:news>\n";
                $out .= "      <news:publication>\n";
                $out .= '        <news:name>' . e($siteName) . "</news:name>\n";
                $out .= "        <news:language>en</news:language>\n";
                $out .= "      </news:publication>\n";
                $out .= '      <news:publication_date>' . e(optional($article->published_at)->toAtomString()) . "</news:publication_date>\n";
                $out .= '      <news:title>' . e($article->title) . "</news:title>\n";
                $out .= "    </news:news>\n";
                $out .= "  </url>\n";
            }
            $out .= '</urlset>';

            return $out;
        });

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * RSS 2.0 feed of the 30 most-recent published articles for
     * syndication, feed readers, and faster crawl discovery.
     */
    public function feed(): Response
    {
        $xml = Cache::remember('seo.feed', 600, function () {
            $siteName = Setting::get('site_name', 'ADT Sports');
            $siteDesc = Setting::get('site_description', "India's #1 Kabaddi media platform.");

            $articles = Article::with('author')
                ->published()->latest('published_at')->limit(30)->get();

            $lastBuild = optional($articles->first()->published_at ?? now())->toRfc822String();

            $out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $out .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
            $out .= "  <channel>\n";
            $out .= '    <title>' . e($siteName) . "</title>\n";
            $out .= '    <link>' . e(url('/')) . "</link>\n";
            $out .= '    <description>' . e($siteDesc) . "</description>\n";
            $out .= "    <language>en</language>\n";
            $out .= '    <lastBuildDate>' . e($lastBuild) . "</lastBuildDate>\n";
            $out .= '    <atom:link href="' . e(url('/feed.xml')) . '" rel="self" type="application/rss+xml" />' . "\n";

            foreach ($articles as $article) {
                $link = route('article', $article->slug);
                $out .= "    <item>\n";
                $out .= '      <title>' . e($article->title) . "</title>\n";
                $out .= '      <link>' . e($link) . "</link>\n";
                $out .= '      <guid isPermaLink="true">' . e($link) . "</guid>\n";
                $out .= '      <pubDate>' . e(optional($article->published_at)->toRfc822String()) . "</pubDate>\n";
                if ($article->category) {
                    $out .= '      <category>' . e($article->category->name) . "</category>\n";
                }
                if (! empty($article->excerpt)) {
                    $out .= '      <description>' . e($article->excerpt) . "</description>\n";
                }
                $out .= "    </item>\n";
            }

            $out .= "  </channel>\n";
            $out .= '</rss>';

            return $out;
        });

        return response($xml, 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    /**
     * Convert a stored (possibly relative) image path into an absolute URL.
     */
    private function absoluteUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://']) ? $path : url($path);
    }
}
