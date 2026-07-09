<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Device;
use App\Models\ImportBatch;
use App\Services\Zkteco\ZktecoDeviceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncBatchToDeviceJob implements ShouldQueue
{
    use Queueable;

    /**
     * Device I/O over UDP can be slow for large user sets; give it room.
     */
    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public int $batchId,
        public int $deviceId,
    ) {}

    public function handle(ZktecoDeviceService $service): void
    {
        $batch = ImportBatch::find($this->batchId);
        $device = Device::find($this->deviceId);

        if ($batch === null || $device === null) {
            return;
        }

        $service->syncBatch($batch, $device);
    }

    public function failed(\Throwable $exception): void
    {
        $batch = ImportBatch::find($this->batchId);

        $batch?->update([
            'status' => ImportBatch::STATUS_FAILED,
            'sync_finished_at' => now(),
        ]);
    }
}
