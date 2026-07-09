<?php

declare(strict_types=1);

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
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

    public function __construct(private ImportedUserValidator $validator = new ImportedUserValidator) {}

    /**
     * @return list<ParsedUserRow>
     */
    public function parse(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $e) {
            throw new RuntimeException('Could not read the spreadsheet: '.$e->getMessage(), previous: $e);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

        if ($rows === []) {
            throw new RuntimeException('The spreadsheet is empty.');
        }

        $header = array_shift($rows);
        $map = $this->mapColumns($header);

        if (! array_key_exists('name', $map)) {
            throw new RuntimeException('No "name" column was found. Add a header row with at least "user_id" and "name".');
        }

        $parsed = [];
        $rowNumber = 1; // header is row 1

        foreach ($rows as $row) {
            $rowNumber++;

            if ($this->isBlankRow($row)) {
                continue;
            }

            $parsed[] = $this->buildRow($rowNumber, $row, $map);
        }

        return $this->flagDuplicateUserIds($parsed);
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
