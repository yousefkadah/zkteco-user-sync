<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\SpreadsheetFactory;
use Tests\TestCase;

class ImportUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_and_stores_an_uploaded_file(): void
    {
        $path = SpreadsheetFactory::xlsx([
            ['user_id', 'name', 'password', 'card_number', 'privilege'],
            ['1001', 'Dana Cohen', '1234', '', 'user'],
            ['1002', 'Yossi Levi', '5678', '', 'user'],
            ['', 'Missing Id', '1', '', ''],
        ]);

        $file = new UploadedFile($path, 'users.xlsx', null, null, true);

        $this->post('/import', ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseCount('import_batches', 1);

        $batch = ImportBatch::first();
        $this->assertSame(3, $batch->total_rows);
        $this->assertSame(2, $batch->valid_rows);
        $this->assertSame(1, $batch->invalid_rows);

        $this->assertDatabaseCount('imported_users', 3);
        $this->assertDatabaseHas('imported_users', [
            'user_id' => '1001',
            'name_ascii' => 'Dana Cohen',
            'is_valid' => true,
        ]);

        @unlink($path);
    }

    public function test_it_rejects_a_non_spreadsheet_file(): void
    {
        $file = UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf');

        $this->post('/import', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('import_batches', 0);
    }
}
