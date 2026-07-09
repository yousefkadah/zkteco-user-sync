<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Models\Device;
use App\Models\ImportBatch;
use App\Models\ImportedUser;
use App\Services\Import\UserSpreadsheetParser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Import/Index', [
            'batches' => ImportBatch::query()
                ->with('device:id,name')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (ImportBatch $batch) => $this->batchSummary($batch)),
            'devices' => Device::orderBy('name')->get(['id', 'name', 'ip_address']),
        ]);
    }

    public function store(StoreImportRequest $request, UserSpreadsheetParser $parser): RedirectResponse
    {
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $tempPath = tempnam(sys_get_temp_dir(), 'zk_import_').'.'.$extension;
        copy($file->getRealPath(), $tempPath);

        try {
            $rows = $parser->parse($tempPath);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        } finally {
            @unlink($tempPath);
        }

        if ($rows === []) {
            return back()->with('error', 'No data rows were found in the file.');
        }

        $validCount = count(array_filter($rows, fn ($row) => $row->isValid()));

        $batch = ImportBatch::create([
            'original_filename' => $file->getClientOriginalName(),
            'total_rows' => count($rows),
            'valid_rows' => $validCount,
            'invalid_rows' => count($rows) - $validCount,
            'status' => ImportBatch::STATUS_PARSED,
        ]);

        $timestamp = now();
        $records = array_map(fn ($row) => [
            'import_batch_id' => $batch->id,
            'row_number' => $row->rowNumber,
            'user_id' => $row->userId,
            'name' => $row->name,
            'name_ascii' => $row->nameAscii,
            'password' => $row->password,
            'card_number' => $row->cardNumber,
            'privilege' => $row->privilege,
            'is_valid' => $row->isValid(),
            'validation_errors' => $row->errors === [] ? null : json_encode($row->errors),
            'sync_status' => ImportedUser::SYNC_PENDING,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $rows);

        foreach (array_chunk($records, 500) as $chunk) {
            ImportedUser::insert($chunk);
        }

        return redirect()
            ->route('import.show', $batch)
            ->with('success', "Imported {$validCount} valid of ".count($rows).' rows.');
    }

    public function show(ImportBatch $batch): Response
    {
        $batch->load('device:id,name');

        $users = $batch->users()
            ->orderBy('row_number')
            ->paginate(100)
            ->through(fn (ImportedUser $user) => $this->userRow($user))
            ->withQueryString();

        return Inertia::render('Import/Show', [
            'batch' => $this->batchSummary($batch),
            'users' => $users,
            'devices' => Device::orderBy('name')->get(['id', 'name', 'ip_address', 'last_connection_ok']),
        ]);
    }

    public function destroy(ImportBatch $batch): RedirectResponse
    {
        $batch->delete();

        return redirect()->route('import.index')->with('success', 'Import deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function batchSummary(ImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'original_filename' => $batch->original_filename,
            'total_rows' => $batch->total_rows,
            'valid_rows' => $batch->valid_rows,
            'invalid_rows' => $batch->invalid_rows,
            'status' => $batch->status,
            'synced_count' => $batch->synced_count,
            'failed_count' => $batch->failed_count,
            'device' => $batch->relationLoaded('device') && $batch->device
                ? ['id' => $batch->device->id, 'name' => $batch->device->name]
                : null,
            'sync_started_at' => $batch->sync_started_at?->toIso8601String(),
            'sync_finished_at' => $batch->sync_finished_at?->toIso8601String(),
            'created_at' => $batch->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userRow(ImportedUser $user): array
    {
        return [
            'id' => $user->id,
            'row_number' => $user->row_number,
            'user_id' => $user->user_id,
            'name' => $user->name,
            'name_ascii' => $user->name_ascii,
            'password' => $user->password,
            'card_number' => $user->card_number,
            'privilege' => $user->privilege,
            'is_valid' => $user->is_valid,
            'validation_errors' => $user->validation_errors ?? [],
            'device_uid' => $user->device_uid,
            'sync_status' => $user->sync_status,
            'sync_error' => $user->sync_error,
        ];
    }
}
