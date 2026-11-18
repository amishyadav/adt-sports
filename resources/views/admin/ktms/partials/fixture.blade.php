{{--
  One match, as a card. Shows the score once the match has started and the
  kick-off time while it is still to come.

  @param $match  App\Models\Ktms\GameMatch
--}}
@php $started = in_array($match->status, \App\Models\Ktms\GameMatch::STARTED_STATUSES, true); @endphp

<a href="{{ route('admin.ktms.matches.show', $match) }}" class="ktms-fixture">
  <div class="ktms-fixture-top">
    <span>
      <span class="badge {{ $match->status_badge }}">{{ $match->status_label }}</span>
      @if($match->round)
        <span style="margin-left:6px">{{ $match->round }}</span>
      @endif
    </span>
    <span>
      @if($match->match_number)Match {{ $match->match_number }}@endif
      @if($match->scheduled_at)
        · {{ $match->scheduled_at->format('d M, H:i') }}
      @endif
    </span>
  </div>

  <div class="ktms-fixture-body">
    <span class="ktms-side">
      @include('admin.ktms.partials.crest', ['team' => $match->homeTeam])
      <span class="ktms-side-name">{{ $match->homeTeam?->name ?? 'TBD' }}</span>
    </span>

    <span class="{{ $started ? 'ktms-fixture-score' : 'ktms-fixture-vs' }}">
      {{ $started ? $match->home_score . ' – ' . $match->away_score : 'VS' }}
    </span>

    <span class="ktms-side away">
      @include('admin.ktms.partials.crest', ['team' => $match->awayTeam])
      <span class="ktms-side-name">{{ $match->awayTeam?->name ?? 'TBD' }}</span>
    </span>
  </div>
</a>
