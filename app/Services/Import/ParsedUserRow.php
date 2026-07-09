<?php

declare(strict_types=1);

namespace App\Services\Import;

class ParsedUserRow
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $rowNumber,
        public string $userId,
        public string $name,
        public string $nameAscii,
        public ?string $password,
        public ?string $cardNumber,
        public string $privilege,
        public array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }
}
