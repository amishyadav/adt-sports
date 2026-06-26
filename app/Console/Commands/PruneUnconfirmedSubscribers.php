<?php

namespace App\Console\Commands;

use App\Models\Subscriber;
use Illuminate\Console\Command;

class PruneUnconfirmedSubscribers extends Command
{
    protected $signature = 'app:prune-unconfirmed-subscribers {--days=30}';

    protected $description = 'Delete abandoned double opt-in subscriber rows (started confirming but never finished) older than N days.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        // Only prune ABANDONED confirmations — rows that started double opt-in
        // (still hold a pending confirmation_token) and never finished. A
        // confirmed subscriber has verified_at set and the token cleared, so
        // legitimate signups are never deleted.
        $deleted = Subscriber::whereNull('verified_at')
            ->whereNotNull('confirmation_token')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} unconfirmed subscriber(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
