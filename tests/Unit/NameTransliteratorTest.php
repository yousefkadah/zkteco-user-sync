<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\NameTransliterator;
use PHPUnit\Framework\TestCase;

class NameTransliteratorTest extends TestCase
{
    public function test_it_passes_ascii_names_through(): void
    {
        $this->assertSame('Dana Cohen', NameTransliterator::toAscii('Dana Cohen'));
    }

    public function test_it_strips_accents_to_plain_ascii(): void
    {
        $result = NameTransliterator::toAscii('José Márquez');

        $this->assertSame('Jose Marquez', $result);
    }

    public function test_it_returns_only_printable_ascii(): void
    {
        $result = NameTransliterator::toAscii('דנה כהן');

        $this->assertMatchesRegularExpression('/^[\x20-\x7E]*$/', $result);
    }

    public function test_it_truncates_to_the_device_field_length(): void
    {
        $long = str_repeat('A', 40);

        $this->assertSame(24, strlen(NameTransliterator::toAscii($long)));
    }

    public function test_it_collapses_whitespace_and_trims(): void
    {
        $this->assertSame('John Doe', NameTransliterator::toAscii('  John   Doe  '));
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', NameTransliterator::toAscii('   '));
    }
}
