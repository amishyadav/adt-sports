<?php

namespace App\Http\Controllers\Ktms;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ktms\Team;
use App\Services\Ktms\PlayerImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Building a squad from a spreadsheet.
 *
 * Scoped to one squad, because a player belongs to a squad and nothing in the
 * file has to say which — so the same file can be reused for another squad
 * without editing.
 */
class PlayerImportController extends Controller
{
    public function __construct(protected PlayerImportService $importer) {}

    /** The upload page. */
    public function form(Team $team): View
    {
        $team->load('tournament');

        return view('admin.ktms.players.import', [
            'team' => $team,
            'columns' => PlayerImportService::COLUMNS,
        ]);
    }

    /** Stream the sample workbook for this squad. */
    public function sample(Team $team): StreamedResponse
    {
        $team->load('tournament');

        $filename = 'ktms-players-'
            .\Illuminate\Support\Str::slug($team->name ?: 'squad')
            .'.xlsx';

        return response()->streamDownload(function () use ($team) {
            $writer = new Xlsx($this->importer->sample($team));
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:2048'],
        ], [
            'file.mimes' => 'Upload an .xlsx, .xls or .csv file.',
            'file.max' => 'The file must be under 2 MB.',
        ]);

        try {
            $parsed = $this->importer->parse($request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'That file could not be read. Re-save it as .xlsx or .csv and try again.');
        }

        if ($parsed['missing'] !== []) {
            return back()->with('error',
                'The file is missing the Name and Jersey Number headings. Start from the sample file.');
        }

        $result = $this->importer->validateRows($team, $parsed['rows']);

        /*
         * Nothing is written when any row is wrong. A half-done import leaves
         * the operator unable to tell which rows landed, so the whole file is
         * handed back with every problem listed instead.
         */
        if ($result['errors'] !== []) {
            return back()
                ->with('error', 'Nothing was imported. Fix the rows below and upload the file again.')
                ->with('import_errors', $result['errors']);
        }

        $created = $this->importer->import($team, $result['players']);

        ActivityLog::record('ktms.player.imported', null,
            'KTMS: imported '.$created.' '.\Illuminate\Support\Str::plural('player', $created)
            .' into "'.$team->name.'"');

        return redirect()
            ->route('admin.ktms.teams.show', $team)
            ->with('success', $created.' '.\Illuminate\Support\Str::plural('player', $created).' imported into '.$team->name.'.');
    }
}
