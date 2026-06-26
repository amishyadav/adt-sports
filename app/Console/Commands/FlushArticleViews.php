<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FlushArticleViews extends Command
{
    protected $signature = 'app:flush-article-views';

    protected $description = 'Fold buffered article view counts into articles.views (without touching updated_at).';

    public function handle(): int
    {
        $rows = DB::table('article_view_buffer')->where('count', '>', 0)->get();

        if ($rows->isEmpty()) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $amount = (int) $row->count;

                // Base query-builder update => no updated_at change => stable sitemap lastmod.
                DB::table('articles')
                    ->where('id', $row->article_id)
                    ->update(['views' => DB::raw('views + ' . $amount)]);

                // Decrement by exactly what we flushed (not delete) so a view that
                // landed between our read and now is preserved, not lost.
                DB::table('article_view_buffer')
                    ->where('article_id', $row->article_id)
                    ->update(['count' => DB::raw('count - ' . $amount)]);
            }

            // Remove only fully-drained rows; any with a concurrent increment survive.
            DB::table('article_view_buffer')->where('count', '<=', 0)->delete();
        });

        $this->info('Flushed view buffers for ' . $rows->count() . ' article(s).');

        return self::SUCCESS;
    }
}
