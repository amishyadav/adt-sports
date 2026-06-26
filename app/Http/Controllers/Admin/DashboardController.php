<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Article, Category, Comment, User, Setting};

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total'       => Article::count(),
            'published'   => Article::where('status', 'published')->count(),
            'drafts'      => Article::where('status', 'draft')->count(),
            'total_views' => Article::sum('views') ?? 0,
            'total_likes' => Article::sum('likes') ?? 0,
            'total_comments' => Comment::where('approved', true)->count(),
            'categories'  => Category::count(),
            'users'       => User::count(),
        ];

        $recent    = Article::with(['category', 'author'])->latest()->limit(6)->get();
        $topViewed = Article::with('category')
            ->withCount(['comments as comments_count' => fn ($q) => $q->where('approved', true)])
            ->where('status', 'published')
            ->orderByDesc('views')
            ->limit(6)->get();

        return view('admin.dashboard', compact('stats', 'recent', 'topViewed'));
    }
}
