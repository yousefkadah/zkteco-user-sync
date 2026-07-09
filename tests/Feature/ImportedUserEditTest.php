<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\ImportedUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportedUserEditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: ImportBatch, 1: ImportedUser}
     */
    private function batchWithUser(array $overrides = []): array
    {
        $batch = ImportBatch::create([
            'original_filename' => 'f.xlsx',
            'total_rows' => 1,
            'valid_rows' => 1,
            'invalid_rows' => 0,
            'status' => ImportBatch::STATUS_PARSED,
        ]);

        $user = $batch->users()->create(array_merge([
            'row_number' => 2,
            'user_id' => '1001',
            'name' => 'Alice',
            'name_ascii' => 'Alice',
            'password' => '1234',
            'privilege' => 'user',
            'is_valid' => true,
        ], $overrides));

        return [$batch, $user];
    }

    public function test_it_adds_a_user_and_recounts(): void
    {
        [$batch] = $this->batchWithUser();

        $this->post("/import/{$batch->id}/users", [
            'user_id' => '2002',
            'name' => 'Bob',
            'password' => '5678',
            'privilege' => 'user',
        ])->assertRedirect();

        $this->assertDatabaseHas('imported_users', [
            'import_batch_id' => $batch->id,
            'user_id' => '2002',
            'name' => 'Bob',
            'is_valid' => true,
        ]);

        $batch->refresh();
        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(2, $batch->valid_rows);
    }

    public function test_editing_a_row_revalidates_and_recounts(): void
    {
        [$batch, $user] = $this->batchWithUser([
            'user_id' => '',
            'is_valid' => false,
            'validation_errors' => ['Missing user id.'],
        ]);
        $batch->update(['valid_rows' => 0, 'invalid_rows' => 1]);

        $this->put("/import/{$batch->id}/users/{$user->id}", [
            'user_id' => '3003',
            'name' => 'Alice',
            'privilege' => 'user',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('3003', $user->user_id);
        $this->assertTrue($user->is_valid);
        $this->assertSame(ImportedUser::SYNC_PENDING, $user->sync_status);

        $batch->refresh();
        $this->assertSame(1, $batch->valid_rows);
        $this->assertSame(0, $batch->invalid_rows);
    }

    public function test_editing_to_a_bad_pin_flags_the_row_invalid(): void
    {
        [$batch, $user] = $this->batchWithUser();

        $this->put("/import/{$batch->id}/users/{$user->id}", [
            'user_id' => '1001',
            'name' => 'Alice',
            'password' => '12ab',
            'privilege' => 'user',
        ])->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->is_valid);
        $this->assertNotEmpty($user->validation_errors);

        $batch->refresh();
        $this->assertSame(0, $batch->valid_rows);
    }

    public function test_a_duplicate_user_id_on_edit_is_flagged(): void
    {
        $batch = ImportBatch::create([
            'original_filename' => 'f.xlsx',
            'total_rows' => 2,
            'valid_rows' => 2,
            'invalid_rows' => 0,
            'status' => ImportBatch::STATUS_PARSED,
        ]);
        $alice = $batch->users()->create(['row_number' => 2, 'user_id' => '1001', 'name' => 'Alice', 'name_ascii' => 'Alice', 'privilege' => 'user', 'is_valid' => true]);
        $bob = $batch->users()->create(['row_number' => 3, 'user_id' => '1002', 'name' => 'Bob', 'name_ascii' => 'Bob', 'privilege' => 'user', 'is_valid' => true]);

        $this->put("/import/{$batch->id}/users/{$bob->id}", [
            'user_id' => '1001',
            'name' => 'Bob',
            'privilege' => 'user',
        ])->assertRedirect();

        $bob->refresh();
        $this->assertFalse($bob->is_valid);
        $this->assertStringContainsString('Duplicate', implode(' ', $bob->validation_errors ?? []));
        $this->assertTrue($alice->fresh()->is_valid);

        $batch->refresh();
        $this->assertSame(1, $batch->valid_rows);
    }

    public function test_it_deletes_a_user_and_recounts(): void
    {
        [$batch, $user] = $this->batchWithUser();

        $this->delete("/import/{$batch->id}/users/{$user->id}")->assertRedirect();

        $this->assertDatabaseMissing('imported_users', ['id' => $user->id]);

        $batch->refresh();
        $this->assertSame(0, $batch->total_rows);
        $this->assertSame(0, $batch->valid_rows);
    }

    public function test_it_rejects_a_user_from_another_batch(): void
    {
        [, $user] = $this->batchWithUser();
        $otherBatch = ImportBatch::create(['original_filename' => 'g.xlsx', 'status' => ImportBatch::STATUS_PARSED]);

        $this->put("/import/{$otherBatch->id}/users/{$user->id}", ['name' => 'X'])->assertNotFound();
    }
}
