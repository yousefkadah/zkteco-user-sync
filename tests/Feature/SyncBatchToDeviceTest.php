<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Models\ImportBatch;
use App\Models\ImportedUser;
use App\Services\Zkteco\ZktecoConnectionFactory;
use App\Services\Zkteco\ZktecoDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Doubles\FakeZkteco;
use Tests\Doubles\FakeZktecoConnectionFactory;
use Tests\TestCase;

class SyncBatchToDeviceTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatchWithUsers(): array
    {
        $device = Device::create(['name' => 'D', 'ip_address' => '192.168.1.201', 'port' => 4370]);

        $batch = ImportBatch::create([
            'original_filename' => 'f.xlsx',
            'total_rows' => 4,
            'valid_rows' => 3,
            'invalid_rows' => 1,
            'status' => ImportBatch::STATUS_SYNCING,
            'sync_started_at' => now(),
        ]);

        $a = ImportedUser::create(['import_batch_id' => $batch->id, 'row_number' => 2, 'user_id' => '1001', 'name' => 'A', 'name_ascii' => 'A', 'password' => '11', 'privilege' => 'user', 'is_valid' => true]);
        $b = ImportedUser::create(['import_batch_id' => $batch->id, 'row_number' => 3, 'user_id' => '5000', 'name' => 'B', 'name_ascii' => 'B', 'password' => '22', 'privilege' => 'user', 'is_valid' => true]);
        $c = ImportedUser::create(['import_batch_id' => $batch->id, 'row_number' => 4, 'user_id' => '1003', 'name' => 'C', 'name_ascii' => 'C', 'password' => '33', 'privilege' => 'user', 'is_valid' => true]);
        $d = ImportedUser::create(['import_batch_id' => $batch->id, 'row_number' => 5, 'user_id' => '', 'name' => 'D', 'name_ascii' => '', 'privilege' => 'user', 'is_valid' => false, 'validation_errors' => ['Missing user id.']]);

        return compact('device', 'batch', 'a', 'b', 'c', 'd');
    }

    private function bindFake(FakeZkteco $fake): ZktecoDeviceService
    {
        $this->app->instance(ZktecoConnectionFactory::class, new FakeZktecoConnectionFactory($fake));

        return $this->app->make(ZktecoDeviceService::class);
    }

    public function test_it_pushes_valid_users_and_records_per_row_results(): void
    {
        ['device' => $device, 'batch' => $batch, 'a' => $a, 'b' => $b, 'c' => $c, 'd' => $d] = $this->makeBatchWithUsers();

        $fake = new FakeZkteco;
        $fake->existingUsers = [['uid' => 1, 'user_id' => '5000']]; // 5000 already exists at slot 1
        $fake->failUserIds = ['1003']; // device rejects this one

        $this->bindFake($fake)->syncBatch($batch->fresh(), $device->fresh());

        $batch->refresh();
        $this->assertSame(ImportBatch::STATUS_COMPLETED, $batch->status);
        $this->assertSame(2, $batch->synced_count);
        $this->assertSame(1, $batch->failed_count);

        // A gets a fresh slot (1 is taken by the existing 5000)
        $this->assertSame('synced', $a->fresh()->sync_status);
        $this->assertSame(2, $a->fresh()->device_uid);

        // B reuses the existing slot for user_id 5000 instead of duplicating
        $this->assertSame('synced', $b->fresh()->sync_status);
        $this->assertSame(1, $b->fresh()->device_uid);

        // C was rejected by the device
        $this->assertSame('failed', $c->fresh()->sync_status);
        $this->assertNotNull($c->fresh()->sync_error);

        // D was invalid, so it is skipped, never pushed
        $this->assertSame('skipped', $d->fresh()->sync_status);

        $this->assertCount(3, $fake->pushed);
        $this->assertFalse($fake->disabled, 'device should be re-enabled after the batch');
    }

    public function test_a_connection_failure_marks_every_valid_user_failed(): void
    {
        ['device' => $device, 'batch' => $batch, 'a' => $a, 'd' => $d] = $this->makeBatchWithUsers();

        $fake = new FakeZkteco;
        $fake->connectResult = false;

        $this->bindFake($fake)->syncBatch($batch->fresh(), $device->fresh());

        $batch->refresh();
        $this->assertSame(ImportBatch::STATUS_FAILED, $batch->status);
        $this->assertSame('failed', $a->fresh()->sync_status);
        $this->assertSame('skipped', $d->fresh()->sync_status);
        $this->assertCount(0, $fake->pushed);
    }
}
