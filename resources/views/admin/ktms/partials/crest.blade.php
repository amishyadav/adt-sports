{{--
  A squad crest: the uploaded image when there is one, otherwise the initials on
  the squad's own colour.

  @param $team  App\Models\Ktms\Team|null
  @param $size  'sm' or 'lg' for the smaller and larger variants
--}}
@php
  $cls = 'ktms-crest' . match ($size ?? '') {
      'sm' => ' ktms-crest-sm',
      'lg' => ' ktms-crest-lg',
      default => '',
  };
@endphp

@if($team && $team->logo_url)
  <img src="{{ $team->logo_url }}" alt="{{ $team->name }}" class="{{ $cls }}" loading="lazy">
@else
  <span class="{{ $cls }} ktms-crest-ph"
        style="background:{{ $team->primary_color ?? '#4A4640' }}">{{ $team->initials ?? '—' }}</span>
@endif
