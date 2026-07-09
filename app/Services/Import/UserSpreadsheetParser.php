<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Support\NameTransliterator;
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
        $userId = $this->cell($row, $map, 'user_id');
        $name = $this->cell($row, $map, 'name');
        $password = $this->cell($row, $map, 'password');
        $cardNumber = $this->cell($row, $map, 'card_number');
        $privilege = $this->normalisePrivilege($this->cell($row, $map, 'privilege'));

        $nameAscii = NameTransliterator::toAscii($name);

        $parsed = new ParsedUserRow(
            rowNumber: $rowNumber,
            userId: $userId,
            name: $name,
            nameAscii: $nameAscii,
            password: $password !== '' ? $password : null,
            cardNumber: $cardNumber !== '' ? $cardNumber : null,
            privilege: $privilege,
        );

        $this->validate($parsed);

        return $parsed;
    }

    private function validate(ParsedUserRow $row): void
    {
        if ($row->userId === '') {
            $row->addError('Missing user id.');
        } elseif (! ctype_digit($row->userId)) {
            $row->addError('User id must contain digits only.');
        } elseif (strlen($row->userId) > 9) {
            $row->addError('User id must be 9 digits or fewer.');
        }

        if (trim($row->name) === '') {
            $row->addError('Missing name.');
        } elseif ($row->nameAscii === '') {
            $row->addError('Name could not be converted to the device character set.');
        }

        if ($row->password !== null) {
            if (! ctype_digit($row->password)) {
                $row->addError('Password/PIN must contain digits only.');
            } elseif (strlen($row->password) > 8) {
                $row->addError('Password/PIN must be 8 digits or fewer.');
            }
        }

        if ($row->cardNumber !== null) {
            if (! ctype_digit($row->cardNumber)) {
                $row->addError('Card number must contain digits only.');
            } elseif (strlen($row->cardNumber) > 10) {
                $row->addError('Card number must be 10 digits or fewer.');
            }
        }
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

    private function normalisePrivilege(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return in_array($value, ['admin', 'administrator', 'manager', 'super', 'super admin', '14', '1', 'yes', 'true', 'מנהל'], true)
            ? 'admin'
            : 'user';
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
