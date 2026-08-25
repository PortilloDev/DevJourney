<?php

declare(strict_types=1);

use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\ChallengeController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PostController;
use App\Http\Controllers\Public\ProjectController;
use App\Http\Controllers\Public\RssController;
use App\Http\Controllers\Public\SitemapController;
use App\Services\VisitTracker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Journal
Route::get('/journal', [PostController::class, 'index'])->name('posts.index');
Route::get('/journal/{post:slug}', [PostController::class, 'show'])->name('posts.show');
Route::get('/category/{category:slug}', [PostController::class, 'byCategory'])->name('posts.category');
Route::get('/tag/{tag:slug}', [PostController::class, 'byTag'])->name('posts.tag');

// Challenges
Route::get('/challenges', [ChallengeController::class, 'index'])->name('challenges.index');
Route::get('/challenges/{challenge:slug}', [ChallengeController::class, 'show'])->name('challenges.show');

// Projects
Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{project:slug}', [ProjectController::class, 'show'])->name('projects.show');

// Static pages
Route::get('/about', [AboutController::class, 'index'])->name('about');
Route::get('/now', [AboutController::class, 'now'])->name('now');
Route::get('/progress', [AboutController::class, 'progress'])->name('progress');

// Feeds
Route::get('/feed', [RssController::class, 'index'])->name('feed');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Session tracking (heartbeat / page-leave) — CSRF-exempt, see bootstrap/app.php.
Route::post('/track/heartbeat', function (Request $request): Response {
    app(VisitTracker::class)->heartbeat($request);

    return response()->noContent();
});
