<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\{Article, Category, Setting, User};
use Illuminate\Http\Request;

class FrontendController extends Controller
{
    private function shared(): array
    {
        return [
            'settings'   => Setting::allAsArray(),
            'categories' => Category::orderBy('name')->get(),
        ];
    }

    public function home(Request $request)
    {
        $perPage   = (int) Setting::get('articles_per_page', 10);
        $catSlug   = $request->get('category');

        $query = Article::with(['category','author'])->published()->latest('published_at');
        if ($catSlug) $query->inCategory($catSlug);

        $articles  = $query->paginate($perPage)->withQueryString();
        $heroLead  = Article::with(['category','author'])->published()->where('featured', true)->latest('published_at')->first()
                     ?? Article::with(['category','author'])->published()->latest('published_at')->first();
        $heroStack = Article::with('category')->published()->latest('published_at')
                     ->where('id', '!=', $heroLead?->id ?? 0)->limit(3)->get();
        $trending  = Article::with('category')->published()->orderByDesc('views')->limit(5)->get();
        $featured  = Article::with(['category','author'])->published()->where('featured',true)->latest('published_at')->limit(3)->get();

        return view('frontend.home', array_merge($this->shared(), compact(
            'articles','heroLead','heroStack','trending','catSlug','featured'
        )));
    }

    public function article(string $slug)
    {
        $article = Article::with(['category','author'])
            ->where('slug', $slug)->firstOrFail();

        if ($article->status === 'published') {
            $article->incrementViews();
        }

        $related  = $article->getRelated(3);
        $trending = Article::with('category')->published()
            ->orderByDesc('views')->where('id','!=',$article->id)->limit(5)->get();

        return view('frontend.article', array_merge($this->shared(), compact(
            'article','related','trending'
        )));
    }

    public function category(string $slug)
    {
        $category = Category::where('slug',$slug)->firstOrFail();
        $perPage  = (int) Setting::get('articles_per_page', 10);
        $articles = Article::with(['category','author'])
            ->published()->where('category_id',$category->id)
            ->latest('published_at')->paginate($perPage);
        $trending = Article::with('category')->published()->orderByDesc('views')->limit(5)->get();

        return view('frontend.category', array_merge($this->shared(), compact(
            'category','articles','trending'
        )));
    }

    public function author(User $user)
    {
        $perPage  = (int) Setting::get('articles_per_page', 10);
        $articles = Article::with(['category','author'])
            ->published()->where('author_id', $user->id)
            ->latest('published_at')->paginate($perPage);

        // Avoid thin/empty author pages being indexed.
        abort_if($articles->total() === 0, 404);

        $trending = Article::with('category')->published()->orderByDesc('views')->limit(5)->get();

        return view('frontend.author', array_merge($this->shared(), compact(
            'user','articles','trending'
        )));
    }

    public function tag(string $tag)
    {
        $perPage  = (int) Setting::get('articles_per_page', 10);
        $articles = Article::with(['category','author'])
            ->published()->whereJsonContains('tags', $tag)
            ->latest('published_at')->paginate($perPage);

        // Avoid thin/empty tag pages being indexed.
        abort_if($articles->total() === 0, 404);

        $trending = Article::with('category')->published()->orderByDesc('views')->limit(5)->get();

        return view('frontend.tag', array_merge($this->shared(), compact(
            'tag','articles','trending'
        )));
    }

    public function search(Request $request)
    {
        $q = trim($request->get('q',''));
        $articles = $q
            ? Article::with(['category','author'])->published()->search($q)->latest('published_at')->paginate(15)->withQueryString()
            : collect();
        $trending = Article::with('category')->published()->orderByDesc('views')->limit(5)->get();

        return view('frontend.search', array_merge($this->shared(), compact('articles','q','trending')));
    }
}
