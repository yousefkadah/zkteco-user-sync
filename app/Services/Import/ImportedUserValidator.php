<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Support\NameTransliterator;

/**
 * Validates and normalises a single device-user record against the ZKTeco field
 * limits. Shared by the spreadsheet parser (bulk import) and the manual
 * add/edit endpoints so both apply exactly the same rules.
 */
class ImportedUserValidator
{
    /**
     * @return array{
     *     user_id: string,
     *     name: string,
     *     name_ascii: string,
     *     password: ?string,
     *     card_number: ?string,
     *     privilege: string,
     *     is_valid: bool,
     *     errors: list<string>
     * }
     */
    public function validate(
        ?string $userId,
        ?string $name,
        ?string $password,
        ?string $cardNumber,
        ?string $privilege,
    ): array {
        $userId = trim((string) $userId);
        $name = trim((string) $name);
        $password = trim((string) $password);
        $cardNumber = trim((string) $cardNumber);
        $privilege = $this->normalisePrivilege((string) $privilege);
        $nameAscii = NameTransliterator::toAscii($name);

        $errors = [];

        if ($userId === '') {
            $errors[] = 'Missing user id.';
        } elseif (! ctype_digit($userId)) {
            $errors[] = 'User id must contain digits only.';
        } elseif (strlen($userId) > 9) {
            $errors[] = 'User id must be 9 digits or fewer.';
        }

        if ($name === '') {
            $errors[] = 'Missing name.';
        } elseif ($nameAscii === '') {
            $errors[] = 'Name could not be converted to the device character set.';
        }

        if ($password !== '') {
            if (! ctype_digit($password)) {
                $errors[] = 'Password/PIN must contain digits only.';
            } elseif (strlen($password) > 8) {
                $errors[] = 'Password/PIN must be 8 digits or fewer.';
            }
        }

        if ($cardNumber !== '') {
            if (! ctype_digit($cardNumber)) {
                $errors[] = 'Card number must contain digits only.';
            } elseif (strlen($cardNumber) > 10) {
                $errors[] = 'Card number must be 10 digits or fewer.';
            }
        }

        return [
            'user_id' => $userId,
            'name' => $name,
            'name_ascii' => $nameAscii,
            'password' => $password !== '' ? $password : null,
            'card_number' => $cardNumber !== '' ? $cardNumber : null,
            'privilege' => $privilege,
            'is_valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    public function normalisePrivilege(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return in_array($value, ['admin', 'administrator', 'manager', 'super', 'super admin', '14', '1', 'yes', 'true', 'מנהל'], true)
            ? 'admin'
            : 'user';
    }
}
