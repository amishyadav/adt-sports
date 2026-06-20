<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Frontend\FrontendController;
use App\Http\Controllers\Frontend\SeoController;

/* ─── PUBLIC FRONTEND ──────────────────────────────────── */
Route::get('/',                  [FrontendController::class, 'home'])->name('home');
Route::get('/article/{slug}',    [FrontendController::class, 'article'])->name('article');
Route::get('/category/{slug}',   [FrontendController::class, 'category'])->name('category');
Route::get('/author/{user}',     [FrontendController::class, 'author'])->name('author');
Route::get('/tag/{tag}',         [FrontendController::class, 'tag'])->name('tag')->where('tag', '[^/]+');
Route::get('/search',            [FrontendController::class, 'search'])->name('search');

/* ─── SEO (robots.txt + sitemap.xml + feed) ────────────── */
Route::get('/robots.txt',        [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml',       [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/news-sitemap.xml',  [SeoController::class, 'newsSitemap'])->name('news-sitemap');
Route::get('/feed.xml',          [SeoController::class, 'feed'])->name('feed');

/* ─── AUTH ─────────────────────────────────────────────── */
Route::get('/admin/login',  [LoginController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [LoginController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout',[LoginController::class, 'logout'])->name('admin.logout');

/* ─── ADMIN PANEL ──────────────────────────────────────── */
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Articles
    Route::get('/articles',                  [ArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/new',              [ArticleController::class, 'create'])->name('articles.create');
    Route::post('/articles',                 [ArticleController::class, 'store'])->name('articles.store');
    Route::get('/articles/{article}/edit',   [ArticleController::class, 'edit'])->name('articles.edit');
    Route::put('/articles/{article}',        [ArticleController::class, 'update'])->name('articles.update');
    Route::delete('/articles/{article}',     [ArticleController::class, 'destroy'])->name('articles.destroy');

    // Categories
    Route::get('/categories',                [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories',               [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}',     [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}',  [CategoryController::class, 'destroy'])->name('categories.destroy');

    // Media
    Route::get('/media',                     [MediaController::class, 'index'])->name('media.index');
    Route::post('/media/upload',             [MediaController::class, 'upload'])->name('media.upload');
    Route::delete('/media/{media}',          [MediaController::class, 'destroy'])->name('media.destroy');

    // Settings
    Route::get('/settings',                  [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings',                  [SettingsController::class, 'update'])->name('settings.update');

    // Users
    Route::get('/users',                     [UserController::class, 'index'])->name('users.index');
    Route::post('/users',                    [UserController::class, 'store'])->name('users.store');
    Route::delete('/users/{user}',           [UserController::class, 'destroy'])->name('users.destroy');
    Route::put('/profile',                   [UserController::class, 'updateProfile'])->name('profile.update');
});
