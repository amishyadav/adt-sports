<?php

namespace App\Services\Ktms;

use App\Models\Ktms\Player;
use App\Models\Ktms\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Creating a squad from a spreadsheet, and the sample file people fill in.
 *
 * Nothing is written until every row has passed validation. A part-finished
 * import is the worst outcome here: the operator cannot tell which rows landed,
 * and fixing it means hunting through the squad by hand. So the whole file is
 * checked first and either all of it is created or none of it is, with the
 * problems reported against the spreadsheet's own row numbers.
 */
class PlayerImportService
{
    /** More than any real squad, but low enough that a wrong file cannot run away. */
    public const MAX_ROWS = 200;

    /**
     * The columns of the sample, in order.
     *
     * @var array<string, array{label: string, hint: string, required: bool}>
     */
    public const COLUMNS = [
        'name' => ['label' => 'Name', 'hint' => "The player's full name", 'required' => true],
        'jersey_number' => ['label' => 'Jersey Number', 'hint' => 'Whole number, unique inside the squad', 'required' => true],
        'role' => ['label' => 'Role', 'hint' => 'Raider, Defender or All-rounder', 'required' => true],
        'position' => ['label' => 'Position', 'hint' => 'Optional. See the Reference sheet', 'required' => false],
        'date_of_birth' => ['label' => 'Date of Birth', 'hint' => 'Optional. YYYY-MM-DD', 'required' => false],
        'height_cm' => ['label' => 'Height (cm)', 'hint' => 'Optional. 100–250', 'required' => false],
        'weight_kg' => ['label' => 'Weight (kg)', 'hint' => 'Optional. 30–200', 'required' => false],
        'nationality' => ['label' => 'Nationality', 'hint' => 'Optional', 'required' => false],
        'is_captain' => ['label' => 'Captain', 'hint' => 'Optional. Yes or No. One per squad', 'required' => false],
        'is_active' => ['label' => 'Active', 'hint' => 'Optional. Yes or No, defaults to Yes', 'required' => false],
    ];

    /**
     * Header spellings accepted on upload, beyond the exact labels above.
     *
     * Keys are the normalised header text — lowercased with everything except
     * letters and digits stripped — so "Jersey No.", "jersey_no" and "JERSEY NO"
     * all arrive here as "jerseyno". People reorder and rename columns, and
     * rejecting a file over a header spelling is a poor reason to fail.
     *
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'name' => 'name', 'playername' => 'name', 'fullname' => 'name', 'player' => 'name',
        'jerseynumber' => 'jersey_number', 'jersey' => 'jersey_number', 'jerseyno' => 'jersey_number',
        'number' => 'jersey_number', 'shirtnumber' => 'jersey_number', 'no' => 'jersey_number',
        'role' => 'role',
        'position' => 'position', 'pos' => 'position',
        'dateofbirth' => 'date_of_birth', 'dob' => 'date_of_birth', 'birthdate' => 'date_of_birth',
        'heightcm' => 'height_cm', 'height' => 'height_cm',
        'weightkg' => 'weight_kg', 'weight' => 'weight_kg',
        'nationality' => 'nationality', 'country' => 'nationality',
        'captain' => 'is_captain', 'iscaptain' => 'is_captain',
        'active' => 'is_active', 'isactive' => 'is_active',
    ];

    /* ─── The sample file ──────────────────────────────────── */

    /**
     * Build the workbook people download, fill in and send back.
     *
     * Two sheets: the one to type into, and a reference listing every accepted
     * value. The Role and Position columns also carry real dropdowns, so most
     * of the ways a file can be wrong are prevented in Excel rather than
     * reported afterwards.
     */
    public function sample(Team $team): Spreadsheet
    {
        $book = new Spreadsheet();

        $book->getProperties()
            ->setTitle('KTMS player import')
            ->setDescription('Fill in one row per player, then upload this file to '.$team->name.'.');

        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Players');

        $keys = array_keys(self::COLUMNS);
        $lastColumn = Coordinate::stringFromColumnIndex(count($keys));

        // Row 1: a reminder of where the file is going.
        $sheet->setCellValue('A1', 'Squad: '.$team->name.'  ·  Tournament: '.($team->tournament->name ?? '—'));
        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Row 2: the headers the importer reads.
        foreach ($keys as $index => $key) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $meta = self::COLUMNS[$key];

            $sheet->setCellValue($column.'2', $meta['label'].($meta['required'] ? ' *' : ''));
            $sheet->getComment($column.'2')->getText()->createTextRun($meta['hint']);
            $sheet->getColumnDimension($column)->setWidth($key === 'name' ? 26 : 16);
        }

        $sheet->getStyle('A2:'.$lastColumn.'2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D4420A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B83808']]],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);

        // Row 3: a note, so nobody wonders whether to overwrite the examples.
        $sheet->setCellValue('A3', 'The two rows below are examples — replace them with your players.');
        $sheet->mergeCells('A3:'.$lastColumn.'3');
        $sheet->getStyle('A3')->getFont()->setItalic(true)->getColor()->setRGB('8A8A8A');

        $examples = [
            ['Pardeep Narwal', 1, 'Raider', 'Center', '1997-02-16', 175, 75, 'India', 'Yes', 'Yes'],
            ['Fazel Atrachali', 2, 'Defender', 'Left Corner', '1992-04-17', 183, 85, 'Iran', 'No', 'Yes'],
        ];

        foreach ($examples as $offset => $row) {
            $sheet->fromArray($row, null, 'A'.(4 + $offset));
        }

        // Dropdowns down the Role and Position columns.
        $this->addDropdown($sheet, $keys, 'role', "Raider,Defender,All-rounder");
        $this->addDropdown($sheet, $keys, 'position', implode(',', array_values(Player::POSITIONS)));
        $this->addDropdown($sheet, $keys, 'is_captain', 'Yes,No');
        $this->addDropdown($sheet, $keys, 'is_active', 'Yes,No');

        // Keep the headers on screen while typing.
        $sheet->freezePane('A4');
        $sheet->setSelectedCell('A4');

        $this->addReferenceSheet($book);

        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function addDropdown($sheet, array $keys, string $field, string $options): void
    {
        $column = Coordinate::stringFromColumnIndex(array_search($field, $keys, true) + 1);

        for ($row = 4; $row <= self::MAX_ROWS + 3; $row++) {
            $validation = $sheet->getCell($column.$row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Not an accepted value');
            $validation->setError('Pick one of the listed options.');
            // Excel wants the list quoted as a single formula string.
            $validation->setFormula1('"'.$options.'"');
        }
    }

    private function addReferenceSheet(Spreadsheet $book): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Reference');
        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(64);

        $rows = [
            ['Column', 'Accepted values'],
            ['Name', 'Required. Any text, up to 255 characters.'],
            ['Jersey Number', 'Required. A whole number 0–999, not already used in this squad.'],
            ['Role', 'Required. Raider, Defender or All-rounder.'],
            ['Position', 'Optional. '.implode(', ', array_values(Player::POSITIONS)).'.'],
            ['Date of Birth', 'Optional. YYYY-MM-DD, or a real date cell. Must be in the past.'],
            ['Height (cm)', 'Optional. A whole number 100–250.'],
            ['Weight (kg)', 'Optional. A whole number 30–200.'],
            ['Nationality', 'Optional. Any text, up to 100 characters.'],
            ['Captain', 'Optional. Yes or No. At most one Yes in the file; it replaces the current captain.'],
            ['Active', 'Optional. Yes or No, defaults to Yes. Inactive players are left out of match lineups.'],
            ['', ''],
            ['Notes', ''],
            ['Row limit', 'Up to '.self::MAX_ROWS.' players per file.'],
            ['Blank rows', 'Ignored, so trailing empty rows are fine.'],
            ['All or nothing', 'If any row has a problem, nothing is created and every problem is listed.'],
            ['Column order', 'Does not matter. Columns are matched on their heading, and extra columns are ignored.'],
            ['File types', '.xlsx, .xls or .csv.'],
        ];

        $sheet->fromArray($rows, null, 'A1');

        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D4420A']],
        ]);
        $sheet->getStyle('A13')->getFont()->setBold(true);
        $sheet->getStyle('A1:B'.count($rows))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    }

    /* ─── Reading an upload ────────────────────────────────── */

    /**
     * Pull the file apart into rows keyed by their spreadsheet row number, so
     * every message can point at a row the operator can actually find.
     *
     * @return array{rows: array<int, array<string, mixed>>, missing: array<int, string>}
     */
    public function parse(UploadedFile $file): array
    {
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        // Not data-only: the formatting is what identifies a real date cell.
        $reader->setReadDataOnly(false);

        $sheet = $reader->load($file->getRealPath())->getSheet(0);

        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        [$map, $headerRow] = $this->findHeaders($sheet, $lastRow, $lastColumn);

        $required = collect(self::COLUMNS)
            ->filter(fn ($meta) => $meta['required'])
            ->keys()
            ->reject(fn ($key) => in_array($key, $map, true))
            ->values()
            ->all();

        if ($headerRow === null || $required !== []) {
            return ['rows' => [], 'missing' => $required ?: array_keys(self::COLUMNS)];
        }

        $rows = [];

        for ($r = $headerRow + 1; $r <= $lastRow; $r++) {
            $data = [];

            foreach ($map as $columnIndex => $key) {
                $data[$key] = $this->cellValue($sheet, $columnIndex, $r);
            }

            // Skip rows where every mapped cell is empty: trailing blanks and
            // the gap under the sample's instruction line are both harmless.
            if (collect($data)->every(fn ($value) => $value === null || $value === '')) {
                continue;
            }

            $rows[$r] = $data;
        }

        return ['rows' => $rows, 'missing' => []];
    }

    /**
     * Locate the header row and map its columns onto our field names.
     *
     * The sample puts a title in row 1, so the headers are not always first;
     * the first row that yields a usable mapping wins.
     *
     * @return array{0: array<int, string>, 1: int|null}
     */
    private function findHeaders($sheet, int $lastRow, int $lastColumn): array
    {
        foreach (range(1, min(10, $lastRow)) as $r) {
            $map = [];

            for ($c = 1; $c <= $lastColumn; $c++) {
                $raw = (string) $sheet->getCell([$c, $r])->getValue();
                $normalised = preg_replace('/[^a-z0-9]/', '', mb_strtolower($raw));

                if ($normalised !== '' && isset(self::HEADER_ALIASES[$normalised])) {
                    // First column wins if a heading somehow appears twice.
                    $map[$c] ??= self::HEADER_ALIASES[$normalised];
                }
            }

            if (in_array('name', $map, true) && in_array('jersey_number', $map, true)) {
                return [$map, $r];
            }
        }

        return [[], null];
    }

    /** One cell, with real date cells turned into YYYY-MM-DD. */
    private function cellValue($sheet, int $column, int $row): mixed
    {
        $cell = $sheet->getCell([$column, $row]);
        $value = $cell->getValue();

        if ($value === null) {
            return null;
        }

        if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        return is_string($value) ? trim($value) : $value;
    }

    /* ─── Validation ───────────────────────────────────────── */

    /**
     * Check every row against the field rules and against each other.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{players: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function validateRows(Team $team, array $rows): array
    {
        $errors = [];
        $players = [];

        if ($rows === []) {
            return ['players' => [], 'errors' => ['The file has no player rows in it.']];
        }

        if (count($rows) > self::MAX_ROWS) {
            return ['players' => [], 'errors' => [
                'The file has '.count($rows).' rows. Split it into files of at most '.self::MAX_ROWS.'.',
            ]];
        }

        $takenJerseys = $team->players()->pluck('jersey_number')->map('intval')->all();
        $seenJerseys = [];
        $captainRows = [];

        foreach ($rows as $rowNumber => $raw) {
            $data = $this->normaliseRow($raw);

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'jersey_number' => ['required', 'integer', 'min:0', 'max:999'],
                'role' => ['required', 'in:'.implode(',', array_keys(Player::ROLES))],
                'position' => ['nullable', 'in:'.implode(',', array_keys(Player::POSITIONS))],
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'height_cm' => ['nullable', 'integer', 'min:100', 'max:250'],
                'weight_kg' => ['nullable', 'integer', 'min:30', 'max:200'],
                'nationality' => ['nullable', 'string', 'max:100'],
                'is_captain' => ['boolean'],
                'is_active' => ['boolean'],
            ], [
                'role.in' => 'Role must be Raider, Defender or All-rounder.',
                'position.in' => 'Position is not one of the accepted values — see the Reference sheet.',
                'date_of_birth.before' => 'Date of birth has to be in the past.',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = "Row {$rowNumber}: {$message}";
                }

                continue;
            }

            $jersey = (int) $data['jersey_number'];

            if (in_array($jersey, $takenJerseys, true)) {
                $errors[] = "Row {$rowNumber}: jersey number {$jersey} is already taken in this squad.";
                continue;
            }

            if (isset($seenJerseys[$jersey])) {
                $errors[] = "Row {$rowNumber}: jersey number {$jersey} is also on row {$seenJerseys[$jersey]}.";
                continue;
            }

            $seenJerseys[$jersey] = $rowNumber;

            if ($data['is_captain']) {
                $captainRows[] = $rowNumber;
            }

            $players[$rowNumber] = $data;
        }

        if (count($captainRows) > 1) {
            $errors[] = 'A squad can only have one captain, but the file marks '
                .count($captainRows).' (rows '.implode(', ', $captainRows).').';
        }

        return ['players' => $players, 'errors' => $errors];
    }

    /**
     * Turn one spreadsheet row into the shape the validator and the model want.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normaliseRow(array $raw): array
    {
        return [
            'name' => $this->text($raw['name'] ?? null),
            'jersey_number' => $this->number($raw['jersey_number'] ?? null),
            'role' => $this->matchOption($raw['role'] ?? null, Player::ROLES),
            'position' => $this->matchOption($raw['position'] ?? null, Player::POSITIONS),
            'date_of_birth' => $this->text($raw['date_of_birth'] ?? null),
            'height_cm' => $this->number($raw['height_cm'] ?? null),
            'weight_kg' => $this->number($raw['weight_kg'] ?? null),
            'nationality' => $this->text($raw['nationality'] ?? null),
            // Absent means "no" for captain and "yes" for active, matching the
            // defaults on the Add Player form.
            'is_captain' => $this->boolean($raw['is_captain'] ?? null, false),
            'is_active' => $this->boolean($raw['is_active'] ?? null, true),
        ];
    }

    private function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    /** Numbers arrive as floats from Excel and as strings from CSV. */
    private function number(mixed $value): ?int
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        // Strip anything that is not part of a number, so "175 cm" still reads.
        $digits = preg_replace('/[^0-9-]/', '', $value);

        return $digits === '' || $digits === '-' ? null : (int) $digits;
    }

    /**
     * Resolve a cell against a key => label list, accepting either side.
     *
     * So Role takes "raider", "Raider" or "RAIDER", and Position takes both
     * "left-corner" and "Left Corner". Anything else is returned untouched and
     * fails validation with a message naming the accepted values.
     *
     * @param  array<string, string>  $options
     */
    private function matchOption(mixed $value, array $options): ?string
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        $needle = preg_replace('/[^a-z0-9]/', '', mb_strtolower($value));

        foreach ($options as $key => $label) {
            foreach ([$key, $label] as $candidate) {
                if (preg_replace('/[^a-z0-9]/', '', mb_strtolower($candidate)) === $needle) {
                    return $key;
                }
            }
        }

        return $value;
    }

    private function boolean(mixed $value, bool $default): bool
    {
        $value = $this->text($value);

        if ($value === null) {
            return $default;
        }

        return in_array(mb_strtolower($value), ['1', 'y', 'yes', 'true', 't', 'active', 'captain'], true);
    }

    /* ─── Writing ──────────────────────────────────────────── */

    /**
     * Create the players. Only ever called with rows that already validated.
     *
     * @param  array<int, array<string, mixed>>  $players
     * @return int  how many were created
     */
    public function import(Team $team, array $players): int
    {
        return DB::transaction(function () use ($team, $players) {
            $created = 0;
            $newCaptainId = null;

            foreach ($players as $data) {
                $player = $team->players()->create($data);

                if ($data['is_captain']) {
                    $newCaptainId = $player->id;
                }

                // Open the aggregate row, as the Add Player screen does, so the
                // reports list the whole squad straight away.
                $player->statisticFor((int) $team->tournament_id);

                $created++;
            }

            // One captain per squad: a Yes in the file takes the armband off
            // whoever had it, which is how the single-player form behaves too.
            if ($newCaptainId) {
                Player::where('team_id', $team->id)
                    ->whereKeyNot($newCaptainId)
                    ->where('is_captain', true)
                    ->update(['is_captain' => false]);
            }

            return $created;
        });
    }
}
