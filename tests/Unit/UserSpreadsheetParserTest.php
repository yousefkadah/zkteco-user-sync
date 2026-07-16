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

    /**
     * @return list<ParsedUserRow>
     */
    private function parseCsv(string $contents): array
    {
        $path = SpreadsheetFactory::csvRaw($contents);
        $this->tempFiles[] = $path;

        return (new UserSpreadsheetParser)->parse($path);
    }

    public function test_it_finds_the_header_below_a_title_row(): void
    {
        $rows = $this->parse([
            ['ZKTeco Users'],
            ['user_id', 'name', 'password'],
            ['1001', 'Dana', '1234'],
        ]);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]->isValid());
        $this->assertSame('1001', $rows[0]->userId);
        // Row number tracks the file's own line, so the data row is line 3.
        $this->assertSame(3, $rows[0]->rowNumber);
    }

    public function test_it_finds_the_header_below_a_leading_blank_row(): void
    {
        $rows = $this->parseCsv("\r\nuser_id,name\r\n1001,Dana\r\n");

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]->isValid());
        $this->assertSame('Dana', $rows[0]->name);
    }

    public function test_it_reads_a_csv_saved_with_lone_cr_line_endings(): void
    {
        // Old-Mac newlines: PHP 8.1+ dropped auto_detect_line_endings, so without
        // normalising these the whole file collapses onto a single line.
        $rows = $this->parseCsv("user_id,name\r1001,Dana\r1002,Yossi\r");

        $this->assertCount(2, $rows);
        $this->assertSame('Dana', $rows[0]->name);
        $this->assertSame('Yossi', $rows[1]->name);
    }

    public function test_it_reads_a_csv_with_a_utf8_bom_and_crlf(): void
    {
        $rows = $this->parseCsv("\xEF\xBB\xBFuser_id,name\r\n1001,Dana\r\n");

        $this->assertCount(1, $rows);
        $this->assertSame('Dana', $rows[0]->name);
    }

    public function test_it_still_rejects_a_file_with_no_name_column(): void
    {
        $this->expectExceptionMessageMatches('/No "name" column was found/');

        $this->parseCsv("foo,bar\r\n1,2\r\n");
    }
}
