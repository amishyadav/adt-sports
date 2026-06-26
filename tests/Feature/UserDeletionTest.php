<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_user_reassigns_their_articles_instead_of_deleting_them(): void
    {
        $admin  = User::factory()->admin()->create();
        $editor = User::factory()->create(['role' => 'editor']);
        $a1 = Article::factory()->create(['author_id' => $editor->id]);
        $a2 = Article::factory()->create(['author_id' => $editor->id]);
        $a2->delete(); // even a trashed article must be preserved + reassigned

        $this->actingAs($admin)->delete(route('admin.users.destroy', $editor))->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $editor->id]);
        $this->assertDatabaseHas('articles', ['id' => $a1->id, 'author_id' => $admin->id]);
        $this->assertSame($admin->id, Article::withTrashed()->find($a2->id)->author_id);
    }

    public function test_db_nulls_author_instead_of_cascade_deleting_articles(): void
    {
        // Safety net: even a raw user delete (bypassing the controller) must NOT
        // destroy the articles — the FK is now nullOnDelete, not cascade.
        $author  = User::factory()->create();
        $article = Article::factory()->create(['author_id' => $author->id]);

        DB::table('users')->where('id', $author->id)->delete();

        $this->assertDatabaseHas('articles', ['id' => $article->id]);
        $this->assertNull($article->fresh()->author_id);
    }
}
