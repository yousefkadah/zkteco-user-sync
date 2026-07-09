<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImportTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_transfer_page_renders_only_valid_users(): void
    {
        $batch = ImportBatch::create([
            'original_filename' => 'f.xlsx',
            'total_rows' => 2,
            'valid_rows' => 1,
            'invalid_rows' => 1,
            'status' => ImportBatch::STATUS_PARSED,
        ]);
        $batch->users()->create(['row_number' => 2, 'user_id' => '1001', 'name' => 'Alice', 'name_ascii' => 'Alice', 'privilege' => 'user', 'is_valid' => true]);
        $batch->users()->create(['row_number' => 3, 'user_id' => '', 'name' => 'Bad', 'name_ascii' => '', 'privilege' => 'user', 'is_valid' => false, 'validation_errors' => ['Missing user id.']]);

        $this->get("/import/{$batch->id}/transfer")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Import/Transfer')
                ->where('batch.valid_rows', 1)
                ->has('users', 1)
                ->where('users.0.name', 'Alice'));
    }
}
