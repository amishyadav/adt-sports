<?php

namespace App\Http\Controllers\Ktms;

use App\Http\Controllers\Controller;
use App\Models\Ktms\Player;
use App\Models\Ktms\PlayerStatistic;
use App\Models\Ktms\Tournament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Player statistics reporting. Every figure is read from the stored
 * ktms_player_statistics aggregates, which the scoring engine keeps current.
 */
class ReportController extends Controller
{
    /** Columns the leaderboard may be ordered by, mapped to their labels. */
    public const SORTABLE = [
        'total_points' => 'Total points',
        'raid_points' => 'Raid points',
        'defense_points' => 'Defence points',
        'total_raids' => 'Total raids',
        'successful_raids' => 'Successful raids',
        'total_defenses' => 'Total defences',
        'successful_defenses' => 'Successful defences',
        'super_raids' => 'Super raids',
        'super_tackles' => 'Super tackles',
        'matches_played' => 'Matches played',
    ];

    public function index(Request $request): View
    {
        return view('admin.ktms.reports.index', [
            'tournaments' => Tournament::orderBy('name')->get(),
            'statistics' => $this->buildQuery($request)->paginate(25)->withQueryString(),
            'sort' => $this->sortColumn($request),
            'tournament' => null,
            'teams' => collect(),
            'summary' => $this->summary($request),
        ]);
    }

    public function tournament(Request $request, Tournament $tournament): View
    {
        // Pin the report to this tournament regardless of the query string.
        $request->merge(['tournament' => $tournament->id]);

        return view('admin.ktms.reports.index', [
            'tournaments' => Tournament::orderBy('name')->get(),
            'statistics' => $this->buildQuery($request)->paginate(25)->withQueryString(),
            'sort' => $this->sortColumn($request),
            'tournament' => $tournament,
            'teams' => $tournament->teams()->orderBy('name')->get(),
            'summary' => $this->summary($request),
        ]);
    }

    /** Stream the current report as CSV so it opens in a spreadsheet. */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->buildQuery($request)->get();
        $filename = 'ktms-player-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'Player', 'Jersey', 'Squad', 'Tournament', 'Role', 'Matches played',
                'Total raids', 'Successful raids', 'Failed raids', 'Empty raids',
                'Touch points', 'Bonus points', 'Raid points', 'Raid success %',
                'Super raids', 'Do-or-die raids', 'Do-or-die conversions',
                'Total defences', 'Successful defences', 'Failed defences',
                'Defence points', 'Defence success %', 'Super tackles',
                'Total points', 'Points per match',
            ]);

            foreach ($rows as $stat) {
                fputcsv($handle, [
                    $stat->player?->name,
                    $stat->player?->jersey_number,
                    $stat->player?->team?->name ?? '',
                    $stat->tournament?->name ?? '',
                    $stat->player?->role_label,
                    $stat->matches_played,
                    $stat->total_raids,
                    $stat->successful_raids,
                    $stat->unsuccessful_raids,
                    $stat->empty_raids,
                    $stat->touch_points,
                    $stat->bonus_points,
                    $stat->raid_points,
                    $stat->raid_success_rate,
                    $stat->super_raids,
                    $stat->do_or_die_raids,
                    $stat->do_or_die_conversions,
                    $stat->total_defenses,
                    $stat->successful_defenses,
                    $stat->failed_defenses,
                    $stat->defense_points,
                    $stat->defense_success_rate,
                    $stat->super_tackles,
                    $stat->total_points,
                    $stat->points_per_match,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return Builder<PlayerStatistic>
     */
    protected function buildQuery(Request $request): Builder
    {
        return $this->applyFilters(PlayerStatistic::query()->with(['player.team', 'tournament']), $request)
            ->orderByDesc($this->sortColumn($request))
            ->orderByDesc('total_points')
            ->orderBy('id');
    }

    /**
     * @param  Builder<PlayerStatistic>  $query
     * @return Builder<PlayerStatistic>
     */
    protected function applyFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('tournament'), fn ($q) => $q->where('tournament_id', $request->integer('tournament')))
            ->when($request->filled('team'), fn ($q) => $q->whereIn(
                'player_id',
                Player::where('team_id', $request->integer('team'))->select('id')
            ))
            ->when($request->filled('role'), fn ($q) => $q->whereIn(
                'player_id',
                Player::where('role', $request->string('role'))->select('id')
            ))
            ->when($request->filled('search'), fn ($q) => $q->whereIn(
                'player_id',
                Player::where('name', 'like', '%'.$request->string('search').'%')->select('id')
            ))
            ->when($request->boolean('played_only'), fn ($q) => $q->where('matches_played', '>', 0));
    }

    protected function sortColumn(Request $request): string
    {
        $sort = (string) $request->string('sort');

        return array_key_exists($sort, self::SORTABLE) ? $sort : 'total_points';
    }

    /**
     * Totals across whatever the current filters select.
     *
     * @return array<string, int|float>
     */
    protected function summary(Request $request): array
    {
        $totals = $this->applyFilters(PlayerStatistic::query(), $request)
            ->selectRaw('COALESCE(SUM(total_raids), 0) AS raids')
            ->selectRaw('COALESCE(SUM(successful_raids), 0) AS successful_raids')
            ->selectRaw('COALESCE(SUM(raid_points), 0) AS raid_points')
            ->selectRaw('COALESCE(SUM(total_defenses), 0) AS defenses')
            ->selectRaw('COALESCE(SUM(successful_defenses), 0) AS successful_defenses')
            ->selectRaw('COALESCE(SUM(defense_points), 0) AS defense_points')
            ->selectRaw('COALESCE(SUM(total_points), 0) AS total_points')
            ->first();

        $raids = (int) ($totals->raids ?? 0);
        $defenses = (int) ($totals->defenses ?? 0);

        return [
            'raids' => $raids,
            'raid_points' => (int) ($totals->raid_points ?? 0),
            'defenses' => $defenses,
            'defense_points' => (int) ($totals->defense_points ?? 0),
            'total_points' => (int) ($totals->total_points ?? 0),
            'raid_success_rate' => $raids > 0
                ? round(((int) ($totals->successful_raids ?? 0) / $raids) * 100, 1)
                : 0.0,
            'defense_success_rate' => $defenses > 0
                ? round(((int) ($totals->successful_defenses ?? 0) / $defenses) * 100, 1)
                : 0.0,
        ];
    }
}
