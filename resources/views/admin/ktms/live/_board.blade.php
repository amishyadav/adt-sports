{{--
  The scoreboard strip at the top of both operator consoles.

  Every value here is also written by ktms-live.js on each snapshot, so the
  server-rendered figures are just the first frame.

  @param $match  App\Models\Ktms\GameMatch
--}}
<div class="ktms-board">
  <div class="ktms-board-side" data-side="home">
    @include('admin.ktms.partials.crest', ['team' => $match->homeTeam, 'size' => 'lg'])
    <div class="ktms-board-name">{{ $match->homeTeam?->name }}</div>
    <div class="ktms-board-score" data-home-score>{{ $match->home_score }}</div>
    <div class="ktms-pips" data-home-pips>
      @for($i = 1; $i <= $match->playersPerSide(); $i++)
        <span class="ktms-pip {{ $i <= $match->home_players_on_court ? 'on' : '' }}"></span>
      @endfor
    </div>
  </div>

  <div class="ktms-board-mid">
    <span class="badge {{ $match->status_badge }}" data-status>{{ $match->status_label }}</span>
    <div class="ktms-board-clock" data-game-clock>
      {{ \App\Models\Ktms\GameMatch::formatClock($match->effectiveGameClockRemaining()) }}
    </div>
    <div class="ktms-board-meta">
      Half <span data-half>{{ $match->current_half }}</span>
      · <span data-raid-count>{{ $match->raid_count }}</span> raids
      · <i class="fa-solid fa-circle ktms-conn" data-connection title="Connecting…"></i>
    </div>
  </div>

  <div class="ktms-board-side" data-side="away">
    @include('admin.ktms.partials.crest', ['team' => $match->awayTeam, 'size' => 'lg'])
    <div class="ktms-board-name">{{ $match->awayTeam?->name }}</div>
    <div class="ktms-board-score" data-away-score>{{ $match->away_score }}</div>
    <div class="ktms-pips" data-away-pips>
      @for($i = 1; $i <= $match->playersPerSide(); $i++)
        <span class="ktms-pip {{ $i <= $match->away_players_on_court ? 'on' : '' }}"></span>
      @endfor
    </div>
  </div>
</div>
