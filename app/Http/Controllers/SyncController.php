<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SyncBatchRequest;
use App\Jobs\SyncBatchToDeviceJob;
use App\Models\Device;
use App\Models\ImportBatch;
use App\Models\ImportedUser;
use Illuminate\Http\RedirectResponse;

class SyncController extends Controller
{
    public function store(SyncBatchRequest $request, ImportBatch $batch): RedirectResponse
    {
        if ($batch->isSyncing()) {
            return back()->with('error', 'This import is already syncing.');
        }

        if ($batch->valid_rows === 0) {
            return back()->with('error', 'There are no valid rows to sync.');
        }

        $device = Device::findOrFail($request->integer('device_id'));

        $batch->users()->where('is_valid', true)->update([
            'sync_status' => ImportedUser::SYNC_PENDING,
            'sync_error' => null,
        ]);

        $batch->update([
            'device_id' => $device->id,
            'status' => ImportBatch::STATUS_SYNCING,
            'synced_count' => 0,
            'failed_count' => 0,
            'sync_started_at' => now(),
            'sync_finished_at' => null,
        ]);

        SyncBatchToDeviceJob::dispatch($batch->id, $device->id);

        return back()->with('success', "Syncing {$batch->valid_rows} users to {$device->name}…");
    }
}
