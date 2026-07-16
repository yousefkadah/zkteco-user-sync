<?php

declare(strict_types=1);

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use RuntimeException;

/**
 * Reads an uploaded .xlsx/.xls/.csv of device users and turns it into validated
 * {@see ParsedUserRow} objects. Column headers are matched loosely (English +
 * Hebrew synonyms) so business users don't have to match an exact template.
 */
class UserSpreadsheetParser
{
    /**
     * Canonical field => list of accepted header aliases (already normalised).
     *
     * @var array<string, list<string>>
     */
    private const HEADER_ALIASES = [
        'user_id' => ['user id', 'userid', 'id', 'employee id', 'employee number', 'number', 'no', 'emp id', 'staff id', 'מספר עובד', 'מספר', 'ת ז', 'תז', 'מזהה'],
        'name' => ['name', 'full name', 'employee', 'employee name', 'first name', 'שם', 'שם עובד', 'שם מלא'],
        'password' => ['password', 'pin', 'pass', 'code', 'pin code', 'סיסמה', 'קוד', 'קוד סודי'],
        'card_number' => ['card', 'card number', 'card no', 'rfid', 'badge', 'כרטיס', 'מספר כרטיס'],
        'privilege' => ['privilege', 'role', 'level', 'access level', 'admin', 'type', 'הרשאה', 'תפקיד', 'רמה'],
    ];

    /**
     * How many leading rows to scan for the header. Files exported by hand often
     * carry a title line or a blank line above the real header row, so we don't
     * assume row 1 is always the header.
     */
    private const HEADER_SCAN_LIMIT = 15;

    public function __construct(private ImportedUserValidator $validator = new ImportedUserValidator) {}

    /**
     * @return list<ParsedUserRow>
     */
    public function parse(string $path): array
    {
        $cleanup = null;

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);

            // A CSV saved with old-Mac ("\r") or mixed line endings collapses onto
            // one line under PHP 8.1+ (which dropped auto_detect_line_endings), so
            // the header row is never found. Normalise first when needed.
            $loadPath = $path;

            if ($reader instanceof Csv) {
                $loadPath = $this->normaliseCsvLineEndings($path);
                $cleanup = $loadPath !== $path ? $loadPath : null;
            }

            $spreadsheet = $reader->load($loadPath);
        } catch (\Throwable $e) {
            throw new RuntimeException($this->readErrorMessage($e), previous: $e);
        } finally {
            if ($cleanup !== null) {
                @unlink($cleanup);
            }
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

        if ($rows === []) {
            throw new RuntimeException('The spreadsheet is empty.');
        }

        [$headerIndex, $map] = $this->locateHeaderRow($rows);

        if ($headerIndex === null) {
            throw new RuntimeException('No "name" column was found. Add a header row with at least "user_id" and "name" (download the Template for the exact format).');
        }

        $parsed = [];

        foreach ($rows as $index => $row) {
            if ($index <= $headerIndex || $this->isBlankRow($row)) {
                continue;
            }

            // 1-based row number matching the file's own line for error messages.
            $parsed[] = $this->buildRow($index + 1, $row, $map);
        }

        return $this->flagDuplicateUserIds($parsed);
    }

    /**
     * Locate the header row. Returns the first row that maps a "name" column
     * (preferring one that also maps another known field, so a stray data row
     * can't win), together with its canonical field => column-index map.
     *
     * @param  list<array<int, mixed>>  $rows
     * @return array{0: int|null, 1: array<string, int>}
     */
    private function locateHeaderRow(array $rows): array
    {
        $fallbackIndex = null;
        $fallbackMap = [];

        $scan = min(count($rows), self::HEADER_SCAN_LIMIT);

        for ($index = 0; $index < $scan; $index++) {
            if ($this->isBlankRow($rows[$index])) {
                continue;
            }

            $map = $this->mapColumns($rows[$index]);

            if (! array_key_exists('name', $map)) {
                continue;
            }

            // A row that maps "name" plus at least one more field is unambiguously
            // the header — take it immediately.
            if (count($map) >= 2) {
                return [$index, $map];
            }

            // Otherwise remember the first name-only header as a fallback.
            if ($fallbackIndex === null) {
                $fallbackIndex = $index;
                $fallbackMap = $map;
            }
        }

        return [$fallbackIndex, $fallbackMap];
    }

    /**
     * Rewrite a CSV with lone-CR (old-Mac) or mixed line endings to LF so
     * PhpSpreadsheet reads its rows. Returns the original path when no rewrite is
     * needed; otherwise a temp file the caller/finally block cleans up.
     */
    private function normaliseCsvLineEndings(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false || $contents === '') {
            return $path;
        }

        // Leave UTF-16/UTF-32 alone — byte-level CR replacement would corrupt the
        // encoding, and PhpSpreadsheet already handles their line endings.
        $bom = substr($contents, 0, 2);

        if ($bom === "\xFF\xFE" || $bom === "\xFE\xFF") {
            return $path;
        }

        // Only act when there is a lone CR (not part of a CRLF pair).
        if (! preg_match("/\r(?!\n)/", $contents)) {
            return $path;
        }

        $normalised = (string) preg_replace("/\r\n?/", "\n", $contents);
        $tmp = (tempnam(sys_get_temp_dir(), 'zk_csvnorm_') ?: $path).'.csv';
        @file_put_contents($tmp, $normalised);

        return $tmp;
    }

    /**
     * Turn a spreadsheet read failure into a user-actionable message. Reading
     * .xlsx works on the bundled runtime (PhpSpreadsheet's Xlsx reader uses
     * SimpleXML + zip, both present), so an unreadable workbook is genuinely a
     * bad file — suggest CSV as the fallback rather than guessing a cause.
     */
    private function readErrorMessage(\Throwable $e): string
    {
        return 'Could not read the spreadsheet: '.$e->getMessage().' If this keeps happening, save the file as CSV and try again.';
    }

    /**
     * @param  array<int, mixed>  $header
     * @return array<string, int> canonical field => column index
     */
    private function mapColumns(array $header): array
    {
        $map = [];

        foreach ($header as $index => $label) {
            $normalised = $this->normaliseHeader((string) ($label ?? ''));

            if ($normalised === '') {
                continue;
            }

            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (isset($map[$field])) {
                    continue;
                }

                if (in_array($normalised, $aliases, true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $map
     */
    private function buildRow(int $rowNumber, array $row, array $map): ParsedUserRow
    {
        $result = $this->validator->validate(
            $this->cell($row, $map, 'user_id'),
            $this->cell($row, $map, 'name'),
            $this->cell($row, $map, 'password'),
            $this->cell($row, $map, 'card_number'),
            $this->cell($row, $map, 'privilege'),
        );

        return new ParsedUserRow(
            rowNumber: $rowNumber,
            userId: $result['user_id'],
            name: $result['name'],
            nameAscii: $result['name_ascii'],
            password: $result['password'],
            cardNumber: $result['card_number'],
            privilege: $result['privilege'],
            errors: $result['errors'],
        );
    }

    /**
     * @param  list<ParsedUserRow>  $rows
     * @return list<ParsedUserRow>
     */
    private function flagDuplicateUserIds(array $rows): array
    {
        $seen = [];

        foreach ($rows as $row) {
            if ($row->userId === '' || ! $row->isValid()) {
                continue;
            }

            if (isset($seen[$row->userId])) {
                $row->addError('Duplicate user id (already used on row '.$seen[$row->userId].').');

                continue;
            }

            $seen[$row->userId] = $row->rowNumber;
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $map
     */
    private function cell(array $row, array $map, string $field): string
    {
        if (! isset($map[$field])) {
            return '';
        }

        $value = $row[$map[$field]] ?? '';

        if (is_float($value) && floor($value) === $value) {
            // PhpSpreadsheet returns numeric cells as floats; keep ids/PINs as plain integers.
            $value = (int) $value;
        }

        return trim((string) $value);
    }

    private function normaliseHeader(string $label): string
    {
        $label = mb_strtolower(trim($label));
        $label = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $label);

        return trim((string) preg_replace('/\s+/', ' ', $label));
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) ($value ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }
}
