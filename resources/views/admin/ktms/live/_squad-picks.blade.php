{{--
  Player picks, split by squad under a labelled heading.

  Both sides number their shirts from 1, so a single flat grid of everyone makes
  it impossible to tell whose #3 is whose — which is exactly the confusion this
  heading removes.

  @param $lineups  ['home' => …, 'away' => …] from MatchStateService::selectablePlayers
  @param $home     the home squad
  @param $away     the away squad
  @param $field    the radio group name, e.g. 'raider_id'
--}}
@foreach(['home' => $home, 'away' => $away] as $side => $team)
  <div class="ktms-squad">
    <div class="ktms-squad-head" style="--squad:{{ $team->primary_color ?? '#4A4640' }}">
      @include('admin.ktms.partials.crest', ['team' => $team, 'size' => 'sm'])
      <span class="ktms-squad-name">{{ $team->name ?? ucfirst($side) }}</span>
      <span class="ktms-squad-side">{{ $side }}</span>
    </div>

    <div class="ktms-picks">
      @forelse($lineups[$side]['on_court'] as $player)
        <label class="ktms-pick" data-team="{{ $team?->id }}">
          <input type="radio" name="{{ $field }}" value="{{ $player->id }}">
          <span class="ktms-pick-no">{{ $player->jersey_number }}</span>
          <span class="ktms-pick-name">{{ $player->name }}</span>
        </label>
      @empty
        <div class="field-hint" style="margin:0">Nobody from this squad is on the mat.</div>
      @endforelse
    </div>
  </div>
@endforeach
