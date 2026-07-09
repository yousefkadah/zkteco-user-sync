<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Import\ParsedUserRow;
use App\Services\Import\UserSpreadsheetParser;
use PHPUnit\Framework\TestCase;
use Tests\Support\SpreadsheetFactory;

class UserSpreadsheetParserTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return list<ParsedUserRow>
     */
    private function parse(array $rows): array
    {
        $path = SpreadsheetFactory::xlsx($rows);
        $this->tempFiles[] = $path;

        return (new UserSpreadsheetParser)->parse($path);
    }

    public function test_it_parses_valid_rows_and_skips_blank_rows(): void
    {
        $rows = $this->parse([
            ['user_id', 'name', 'password', 'card_number', 'privilege'],
            ['1001', 'Dana Cohen', '1234', '', 'user'],
            ['', '', '', '', ''],
            ['1002', 'Site Admin', '9999', '', 'admin'],
        ]);

        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]->isValid());
        $this->assertSame('1001', $rows[0]->userId);
        $this->assertSame('Dana Cohen', $rows[0]->nameAscii);
        $this->assertSame('admin', $rows[1]->privilege);
    }

    public function test_it_maps_header_aliases(): void
    {
        $rows = $this->parse([
            ['Employee ID', 'Full Name', 'PIN'],
            ['7', 'Bob', '321'],
        ]);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]->isValid());
        $this->assertSame('7', $rows[0]->userId);
        $this->assertSame('321', $rows[0]->password);
    }

    public function test_it_flags_invalid_rows(): void
    {
        $rows = $this->parse([
            ['user_id', 'name', 'password'],
            ['', 'No Id', '12'],
            ['1004', 'Bad Pin', '12ab'],
            ['1234567890', 'Long Id', '1'],
        ]);

        $this->assertFalse($rows[0]->isValid());
        $this->assertStringContainsString('Missing user id', implode(' ', $rows[0]->errors));

        $this->assertFalse($rows[1]->isValid());
        $this->assertStringContainsString('digits only', implode(' ', $rows[1]->errors));

        $this->assertFalse($rows[2]->isValid());
        $this->assertStringContainsString('9 digits or fewer', implode(' ', $rows[2]->errors));
    }

    public function test_it_flags_duplicate_user_ids(): void
    {
        $rows = $this->parse([
            ['user_id', 'name', 'password'],
            ['1001', 'First', '1'],
            ['1001', 'Second', '2'],
        ]);

        $this->assertTrue($rows[0]->isValid());
        $this->assertFalse($rows[1]->isValid());
        $this->assertStringContainsString('Duplicate', implode(' ', $rows[1]->errors));
    }
}
