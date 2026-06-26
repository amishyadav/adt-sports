<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Comment;
use App\Support\PublicCache;

class CommentController extends Controller
{
    public function index()
    {
        $pending  = Comment::pending()->with('article')->latest()->get();
        $approved = Comment::approved()->with('article')->latest()->paginate(30);

        return view('admin.comments.index', compact('pending', 'approved'));
    }

    /** Restore a previously hidden comment back to the public page. */
    public function approve(Comment $comment)
    {
        $comment->update(['approved' => true]);

        // It's back on the (cached) public article page — evict just that page.
        if ($comment->article) {
            PublicCache::forgetArticle($comment->article);
        }

        ActivityLog::record('comment.approved', $comment, 'Restored a comment by ' . $comment->author_name);

        return back()->with('success', "Comment restored — it's live again.");
    }

    /** Hide a live comment from the public page (reversible — kept for review). */
    public function hide(Comment $comment)
    {
        $comment->update(['approved' => false]);

        if ($comment->article) {
            PublicCache::forgetArticle($comment->article);
        }

        ActivityLog::record('comment.hidden', $comment, 'Hid a comment by ' . $comment->author_name);

        return back()->with('success', 'Comment hidden from the public page.');
    }

    public function destroy(Comment $comment)
    {
        $wasApproved = $comment->approved;
        $authorName = $comment->author_name;
        $article = $comment->article; // capture before the row is gone
        $comment->delete();

        // Only an approved comment was on the public page, so only then must we
        // evict it — and only that one article's page.
        if ($wasApproved && $article) {
            PublicCache::forgetArticle($article);
        }

        ActivityLog::record('comment.deleted', null, 'Deleted a comment by ' . $authorName);

        return back()->with('success', 'Comment deleted.');
    }
}
