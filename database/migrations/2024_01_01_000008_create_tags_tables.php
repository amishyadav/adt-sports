<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamps();
        });

        Schema::create('article_tag', function (Blueprint $t) {
            $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $t->primary(['article_id', 'tag_id']);
        });

        $this->backfillFromJson();
    }

    /**
     * Migrate the legacy articles.tags JSON column into the normalized
     * tags + article_tag tables. Case/whitespace are canonicalized via the
     * slug so "PKL" and "pkl" collapse to a single tag. No-op on a fresh DB.
     */
    private function backfillFromJson(): void
    {
        if (! Schema::hasColumn('articles', 'tags')) {
            return;
        }

        $slugToId = [];

        foreach (DB::table('articles')->select('id', 'tags')->cursor() as $row) {
            $names = json_decode((string) $row->tags, true);
            if (! is_array($names)) {
                continue;
            }

            foreach ($names as $name) {
                $name = trim(preg_replace('/\s+/', ' ', (string) $name));
                if ($name === '') {
                    continue;
                }

                $slug = Str::slug($name);
                if ($slug === '') {
                    continue;
                }

                if (! isset($slugToId[$slug])) {
                    $slugToId[$slug] = DB::table('tags')->insertGetId([
                        'name'       => $name,
                        'slug'       => $slug,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('article_tag')->insertOrIgnore([
                    'article_id' => $row->id,
                    'tag_id'     => $slugToId[$slug],
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('article_tag');
        Schema::dropIfExists('tags');
    }
};
