<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Fold buffered page views into articles.views off the request path.
Schedule::command('app:flush-article-views')->everyFiveMinutes()->withoutOverlapping();

// Clear out unconfirmed (never-verified) subscriber rows.
Schedule::command('app:prune-unconfirmed-subscribers')->weekly();

// Mail (and any other job) runs on the queue now — keep failed_jobs from growing
// without bound. Delivery failures still surface via CommenterConfirmation::failed()
// + Sentry; this only prunes the persisted records after a week.
Schedule::command('queue:prune-failed --hours=168')->daily();
