<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TeamMatchController;
use App\Http\Controllers\Admin\MatchScreenController;
use App\Http\Controllers\Frontend\FrontendController;
use App\Http\Controllers\Frontend\SeoController;
use App\Http\Controllers\Frontend\SubscriberController as PublicSubscriberController;
use App\Http\Controllers\Admin\SubscriberController as AdminSubscriberController;
use App\Http\Controllers\Frontend\CommentController as PublicCommentController;
use App\Http\Controllers\Frontend\CommenterController;
use App\Http\Controllers\Admin\CommentController as AdminCommentController;

/* ─── PUBLIC FRONTEND ──────────────────────────────────── */
Route::get('/',                  [FrontendController::class, 'home'])->name('home');
Route::get('/article/{slug}',    [FrontendController::class, 'article'])->name('article');
Route::get('/article/{article}/hit', [FrontendController::class, 'hit'])->name('article.hit')->middleware('throttle:60,1');
Route::post('/article/{article}/like', [FrontendController::class, 'like'])->name('article.like')->middleware('throttle:30,1');
Route::get('/category/{slug}',   [FrontendController::class, 'category'])->name('category');
Route::get('/author/{user}',     [FrontendController::class, 'author'])->name('author');
Route::get('/tag/{tag:slug}',    [FrontendController::class, 'tag'])->name('tag');
Route::get('/search',            [FrontendController::class, 'search'])->middleware('throttle:30,1')->name('search');
Route::post('/subscribe',        [PublicSubscriberController::class, 'store'])->middleware('throttle:4,1')->name('subscribe');
Route::match(['GET', 'POST'], '/subscribe/confirm/{subscriber}', [PublicSubscriberController::class, 'confirm'])->middleware('signed')->name('subscribe.confirm');
Route::post('/article/{article}/comments', [PublicCommentController::class, 'store'])->middleware('throttle:5,1')->name('article.comments.store');
Route::post('/article/{article}/commenter', [CommenterController::class, 'subscribe'])->middleware('throttle:4,1')->name('article.commenter.subscribe');
Route::match(['GET', 'POST'], '/article/{article}/commenter/confirm/{subscriber}', [CommenterController::class, 'confirm'])->middleware('signed')->name('article.commenter.confirm');
Route::post('/article/{article}/commenter/forget', [CommenterController::class, 'signOut'])->name('article.commenter.forget');

/* ─── SEO (robots.txt + sitemap.xml + feed) ────────────── */
Route::get('/robots.txt',        [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml',       [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/news-sitemap.xml',  [SeoController::class, 'newsSitemap'])->name('news-sitemap');
Route::get('/feed.xml',          [SeoController::class, 'feed'])->name('feed');

/* ─── AUTH ─────────────────────────────────────────────── */
Route::get('/admin/login',  [LoginController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('admin.login.post');
Route::post('/admin/logout',[LoginController::class, 'logout'])->name('admin.logout');

// Password reset (Laravel broker; route names match the framework defaults).
Route::get('/admin/forgot-password',       [PasswordResetController::class, 'showForgot'])->name('password.request');
Route::post('/admin/forgot-password',      [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
Route::get('/admin/reset-password/{token}',[PasswordResetController::class, 'showReset'])->name('password.reset');
Route::post('/admin/reset-password',       [PasswordResetController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');

/* ─── ADMIN PANEL ──────────────────────────────────────── */
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Articles
    Route::get('/articles',                  [ArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/new',              [ArticleController::class, 'create'])->name('articles.create');
    Route::get('/articles/trash',            [ArticleController::class, 'trash'])->name('articles.trash');
    Route::post('/articles',                 [ArticleController::class, 'store'])->name('articles.store');
    Route::post('/articles/bulk',            [ArticleController::class, 'bulk'])->name('articles.bulk');
    Route::get('/articles/{article}/edit',   [ArticleController::class, 'edit'])->name('articles.edit');
    Route::put('/articles/{article}',        [ArticleController::class, 'update'])->name('articles.update');
    Route::get('/articles/{article}/revisions/{revision}', [ArticleController::class, 'revision'])->name('articles.revision');
    Route::delete('/articles/{article}',     [ArticleController::class, 'destroy'])->name('articles.destroy');
    Route::put('/articles/{id}/restore',     [ArticleController::class, 'restore'])->name('articles.restore');
    Route::delete('/articles/{id}/force',    [ArticleController::class, 'forceDestroy'])->name('articles.force');

    // Categories — list is staff-visible; writes are admin-only (see group below).
    Route::get('/categories',                [CategoryController::class, 'index'])->name('categories.index');

    // Teams & matches — lists are staff-visible; writes are admin-only (see group below).
    Route::get('/teams',                     [TeamController::class, 'index'])->name('teams.index');
    Route::get('/matches',                   [TeamMatchController::class, 'index'])->name('matches.index');

    // Match-day screens (Action column on the matches list). Read-only, so staff
    // can run a scoreboard without full admin rights; the controls below are not.
    Route::get('/matches/{match}/scoreboard', [MatchScreenController::class, 'scoreboard'])->name('matches.scoreboard');
    Route::get('/matches/{match}/timer',      [MatchScreenController::class, 'timer'])->name('matches.timer');
    Route::get('/matches/{match}/screen',     [MatchScreenController::class, 'mainScreen'])->name('matches.screen');
    Route::get('/matches/{match}/live',       [MatchScreenController::class, 'liveScore'])->name('matches.live');
    Route::get('/matches/{match}/state',      [MatchScreenController::class, 'state'])->name('matches.state');

    // Media
    Route::get('/media',                     [MediaController::class, 'index'])->name('media.index');
    Route::post('/media/upload',             [MediaController::class, 'upload'])->name('media.upload');
    Route::put('/media/{media}',             [MediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{media}',          [MediaController::class, 'destroy'])->name('media.destroy');

    // Settings index hosts the per-user Profile tab, so it stays staff-accessible;
    // the global-settings mutation is admin-only (see group below).
    Route::get('/settings',                  [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/profile',                   [UserController::class, 'updateProfile'])->name('profile.update');

    /* ─── ADMIN-ONLY (full administrators) ─────────────────── */
    Route::middleware('admin.only')->group(function () {
        // Category taxonomy
        Route::post('/categories',               [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}',     [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}',  [CategoryController::class, 'destroy'])->name('categories.destroy');

        // Teams
        Route::post('/teams',                    [TeamController::class, 'store'])->name('teams.store');
        Route::put('/teams/{team}',              [TeamController::class, 'update'])->name('teams.update');
        Route::put('/teams/{team}/toggle',       [TeamController::class, 'toggleStatus'])->name('teams.toggle');
        Route::delete('/teams/{team}',           [TeamController::class, 'destroy'])->name('teams.destroy');

        // Team matches
        Route::post('/matches',                  [TeamMatchController::class, 'store'])->name('matches.store');
        Route::put('/matches/{match}',           [TeamMatchController::class, 'update'])->name('matches.update');
        Route::delete('/matches/{match}',        [TeamMatchController::class, 'destroy'])->name('matches.destroy');

        // Scoreboard / timer console controls
        Route::put('/matches/{match}/score',     [MatchScreenController::class, 'updateScore'])->name('matches.score');
        Route::put('/matches/{match}/adjust',    [MatchScreenController::class, 'adjustScore'])->name('matches.adjust');
        Route::put('/matches/{match}/event',     [MatchScreenController::class, 'recordEvent'])->name('matches.event');
        Route::put('/matches/{match}/players',   [MatchScreenController::class, 'updatePlayers'])->name('matches.players');
        Route::put('/matches/{match}/game-part', [MatchScreenController::class, 'updateGamePart'])->name('matches.gamepart');
        Route::put('/matches/{match}/swap',      [MatchScreenController::class, 'swapCourts'])->name('matches.swap');
        Route::put('/matches/{match}/timer',     [MatchScreenController::class, 'updateTimer'])->name('matches.timer.update');
        Route::put('/matches/{match}/raid',      [MatchScreenController::class, 'updateRaid'])->name('matches.raid');
        Route::put('/matches/{match}/status',    [MatchScreenController::class, 'updateStatus'])->name('matches.status');
        Route::put('/matches/{match}/undo',      [MatchScreenController::class, 'undo'])->name('matches.undo');
        Route::put('/matches/{match}/reset',     [MatchScreenController::class, 'resetMatch'])->name('matches.reset');

        // Global settings
        Route::put('/settings',                  [SettingsController::class, 'update'])->name('settings.update');

        // Comment moderation — full administrators only
        Route::get('/comments',                  [AdminCommentController::class, 'index'])->name('comments.index');
        Route::put('/comments/{comment}/approve',[AdminCommentController::class, 'approve'])->name('comments.approve');
        Route::put('/comments/{comment}/hide',   [AdminCommentController::class, 'hide'])->name('comments.hide');
        Route::delete('/comments/{comment}',     [AdminCommentController::class, 'destroy'])->name('comments.destroy');

        // Users
        Route::get('/users',                     [UserController::class, 'index'])->name('users.index');
        Route::post('/users',                    [UserController::class, 'store'])->name('users.store');
        Route::delete('/users/{user}',           [UserController::class, 'destroy'])->name('users.destroy');

        // Newsletter subscribers (PII — full admins only)
        Route::get('/subscribers',               [AdminSubscriberController::class, 'index'])->name('subscribers.index');
        Route::get('/subscribers/export',        [AdminSubscriberController::class, 'export'])->name('subscribers.export');

        // Audit trail (full admins only)
        Route::get('/activity',                  [ActivityController::class, 'index'])->name('activity.index');
    });
});
