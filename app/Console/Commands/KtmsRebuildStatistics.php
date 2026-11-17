<?php

namespace App\Console\Commands;

use App\Models\Ktms\Tournament;
use App\Services\Ktms\StatisticsService;
use Illuminate\Console\Command;

/**
 * Rebuild the KTMS player aggregates from the stored raid and defensive-action
 * rows.
 *
 * The scoring engine already keeps these current after every action, so this is
 * a repair tool: useful after a direct database edit, or to prove the
 * aggregates and the underlying records still agree.
 */
class KtmsRebuildStatistics extends Command
{
    protected $signature = 'ktms:rebuild-stats
                            {--tournament= : Limit the rebuild to a single tournament id}';

    protected $description = 'Rebuild KTMS player statistics from the stored raid and defensive action records';

    public function handle(StatisticsService $statistics): int
    {
        $tournaments = Tournament::query()
            ->when($this->option('tournament'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        if ($tournaments->isEmpty()) {
            $this->warn('No KTMS tournaments found.');

            return self::SUCCESS;
        }

        foreach ($tournaments as $tournament) {
            $count = $statistics->recalculateTournament($tournament);
            $this->line("Rebuilt {$count} player records for \"{$tournament->name}\".");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
