@extends('layouts.admin')
@section('title','Import Players')
@section('content')

<div class="page-hd">
  <div>
    <h1>Import Players</h1>
    <div class="page-hd-sub">{{ $team->name }} · {{ $team->tournament->name }}</div>
  </div>
  <div>
    <a href="{{ route('admin.ktms.teams.show', $team) }}" class="btn btn-ghost">
      <i class="fa-solid fa-arrow-left"></i> Back to squad
    </a>
  </div>
</div>

@if(session('import_errors'))
  <div class="table-wrap" style="margin-bottom:16px;border-color:rgba(220,38,38,.35)">
    <div class="table-hd" style="border-color:rgba(220,38,38,.35)">
      <h3 style="color:#FCA5A5">
        <i class="fa-solid fa-circle-exclamation"></i>
        {{ count(session('import_errors')) }} {{ Str::plural('problem', count(session('import_errors'))) }} found — nothing was imported
      </h3>
    </div>
    <div style="padding:14px">
      <ul style="display:grid;gap:7px;list-style:none">
        @foreach(session('import_errors') as $problem)
          <li style="display:flex;gap:9px;font-size:12.5px;color:var(--ink2);line-height:1.5">
            <i class="fa-solid fa-xmark" style="color:#FCA5A5;margin-top:3px;flex-shrink:0"></i>
            <span>{{ $problem }}</span>
          </li>
        @endforeach
      </ul>
      <div class="field-hint" style="margin-top:12px">
        Row numbers are the spreadsheet's own, so you can go straight to them.
      </div>
    </div>
  </div>
@endif

<div class="split-grid">
  <div>
    <div class="panel-card">
      <h4>Step 1 — Get The Sample</h4>
      <p style="font-size:12.5px;color:var(--ink2);line-height:1.6;margin-bottom:12px">
        The sample is set up for this squad: the right headings, two example
        rows to replace, dropdowns on Role and Position, and a Reference sheet
        listing every accepted value.
      </p>
      <a href="{{ route('admin.ktms.players.import.sample', $team) }}" class="btn btn-ghost btn-lg">
        <i class="fa-solid fa-file-arrow-down"></i> Download Sample (.xlsx)
      </a>
    </div>

    <div class="panel-card">
      <h4>Step 2 — Upload It Back</h4>

      <form action="{{ route('admin.ktms.players.import.store', $team) }}" method="POST"
            enctype="multipart/form-data" id="importForm">
        @csrf

        <div class="field">
          <label>Player File *</label>
          <div class="ktms-drop" onclick="document.getElementById('importFile').click()">
            <i class="fa-solid fa-file-excel"></i>
            <span class="ktms-drop-text" id="importFileName">Choose an .xlsx, .xls or .csv file</span>
          </div>
          <input type="file" name="file" id="importFile" required
                 accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                 onchange="showImportFile(this)" style="display:none">
          <div class="field-hint">Up to 2 MB and {{ \App\Services\Ktms\PlayerImportService::MAX_ROWS }} players per file.</div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" id="importSubmit">
          <i class="fa-solid fa-upload"></i> Import Players
        </button>
      </form>

      <div class="field-hint" style="margin-top:12px">
        Every row is checked before anything is created. If one row has a
        problem, nothing is imported and you get the full list — so a file is
        never half-applied.
      </div>
    </div>
  </div>

  <div>
    <div class="panel-card">
      <h4>Columns</h4>
      <table style="width:100%">
        <tbody>
          @foreach($columns as $key => $meta)
            <tr>
              <td style="padding:7px 0;border-bottom:1px solid var(--border2)">
                <span style="font-size:12.5px;font-weight:500;color:var(--ink)">{{ $meta['label'] }}</span>
                @if($meta['required'])
                  <span style="color:var(--brand)">*</span>
                @endif
                <div style="font-size:11px;color:var(--ink3);line-height:1.5">{{ $meta['hint'] }}</div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div class="field-hint" style="margin-top:10px">
        Column order does not matter — headings are matched by name, and extra
        columns are ignored.
      </div>
    </div>

    <div class="panel-card">
      <h4>Current Squad</h4>
      <p style="font-size:12.5px;color:var(--ink2);line-height:1.6">
        {{ $team->players()->count() }} {{ Str::plural('player', $team->players()->count()) }} already registered.
        Imported players are added alongside them; nobody is replaced.
      </p>
      @if($team->players()->count() > 0)
        <div class="field-hint" style="margin-top:8px">
          Jersey numbers in use:
          {{ $team->players()->orderBy('jersey_number')->pluck('jersey_number')->implode(', ') }}
        </div>
      @endif
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  .ktms-drop{display:flex;align-items:center;gap:12px;padding:18px;border:1px dashed var(--border);border-radius:10px;background:var(--card);cursor:pointer;transition:border-color .12s,background .12s}
  .ktms-drop:hover{border-color:var(--brand);background:rgba(212,66,10,.05)}
  .ktms-drop i{font-size:24px;color:#86EFAC;flex-shrink:0}
  .ktms-drop-text{font-size:13px;color:var(--ink2);word-break:break-all}
  .ktms-drop.has-file{border-style:solid;border-color:rgba(22,163,74,.4)}
  .ktms-drop.has-file .ktms-drop-text{color:var(--ink);font-weight:500}
</style>
@endpush

@push('scripts')
<script>
function showImportFile(input) {
  var file = input.files && input.files[0];
  var label = document.getElementById('importFileName');
  var drop = document.querySelector('.ktms-drop');

  if (!file) {
    label.textContent = 'Choose an .xlsx, .xls or .csv file';
    drop.classList.remove('has-file');
    return;
  }

  label.textContent = file.name + '  (' + Math.max(1, Math.round(file.size / 1024)) + ' KB)';
  drop.classList.add('has-file');
}

/* Parsing and validating a file takes a moment; without this the operator has
   no sign anything happened and tends to submit twice. */
document.getElementById('importForm').addEventListener('submit', function () {
  var button = document.getElementById('importSubmit');
  button.disabled = true;
  button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking the file…';
});
</script>
@endpush
