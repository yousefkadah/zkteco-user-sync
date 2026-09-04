<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\FullnessConnection;
use App\Models\ImportBatch;
use App\Models\ImportedUser;
use App\Services\Fullness\FullnessClient;
use App\Services\Import\ImportedUserValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connectors — connect this app to a Fullness CRM account, pick the business,
 * and pull its assigned attendance users into a normal import batch so the
 * existing Transfer → device-sync pipeline can push them to a ZKTeco device.
 */
class FullnessConnectionController extends Controller
{
    private const DEFAULT_BASE_URL = 'https://fullness.co.il';

    public function __construct(private readonly FullnessClient $client) {}

    public function index(): Response
    {
        $connection = FullnessConnection::current();

        return Inertia::render('Connectors/Index', [
            'connection' => $connection ? [
                'base_url' => $connection->base_url,
                'owner_email' => $connection->owner_email,
                'tenant_id' => $connection->tenant_id,
                'tenant_name' => $connection->tenant_name,
                'fullness_device_id' => $connection->fullness_device_id,
                'fullness_device_name' => $connection->fullness_device_name,
                'last_connected_at' => $connection->last_connected_at?->toIso8601String(),
                'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
            ] : null,
            'tenants' => $connection?->tenants ?? [],
            'devices' => $connection?->devices ?? [],
            'defaultBaseUrl' => self::DEFAULT_BASE_URL,
            'deviceCount' => Device::count(),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'base_url' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->client->login(
                $validated['base_url'],
                $validated['email'],
                $validated['password'],
                $this->deviceName(),
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $tenants = $result['tenants'];
        if ($tenants === []) {
            return back()->with('error', 'This account has no businesses you can manage.');
        }

        // Auto-select when the owner has exactly one business.
        $selected = count($tenants) === 1 ? $tenants[0] : null;

        // Single-connection app: replace any previous connection.
        FullnessConnection::query()->delete();
        $connection = FullnessConnection::create([
            'base_url' => $validated['base_url'],
            'token' => $result['token'],
            'owner_email' => $result['owner_email'],
            'tenants' => $tenants,
            'tenant_id' => $selected['id'] ?? null,
            'tenant_name' => $selected['name'] ?? null,
            'last_connected_at' => now(),
        ]);

        if (! $selected) {
            return back()->with('success', 'Connected. Choose a business to continue.');
        }

        // The single business was auto-selected — load its devices so the
        // operator can pick which one to sync.
        if ($error = $this->loadTenantDevices($connection)) {
            return back()->with('error', "Connected, but couldn't load devices: {$error}");
        }

        return back()->with('success', "Connected to {$selected['name']}.");
    }

    public function selectTenant(Request $request): RedirectResponse
    {
        $connection = FullnessConnection::current();
        if (! $connection || ! $connection->isConnected()) {
            return back()->with('error', 'Connect to Fullness first.');
        }

        $tenantId = (string) $request->input('tenant_id');
        $tenant = collect($connection->tenants ?? [])->firstWhere('id', $tenantId);
        if (! $tenant) {
            return back()->with('error', 'Unknown business.');
        }

        // Switching business resets any prior device selection.
        $connection->update([
            'tenant_id' => $tenant['id'],
            'tenant_name' => $tenant['name'],
            'devices' => null,
            'fullness_device_id' => null,
            'fullness_device_name' => null,
        ]);

        if ($error = $this->loadTenantDevices($connection)) {
            return back()->with('error', $error);
        }

        $connection->refresh();

        return back()->with('success', $connection->fullness_device_name
            ? "Business set to {$tenant['name']} — device “{$connection->fullness_device_name}”."
            : "Business set to {$tenant['name']}. Choose a device.");
    }

    public function selectDevice(Request $request): RedirectResponse
    {
        $connection = FullnessConnection::current();
        if (! $connection || ! $connection->hasTenant()) {
            return back()->with('error', 'Choose a business first.');
        }

        $deviceId = (string) $request->input('device_id');
        $device = collect($connection->devices ?? [])
            ->first(fn ($d) => is_array($d) && (string) ($d['id'] ?? '') === $deviceId);
        if (! $device) {
            return back()->with('error', 'Unknown device.');
        }

        $connection->update([
            'fullness_device_id' => (string) $device['id'],
            'fullness_device_name' => $device['name'] ?? null,
        ]);

        return back()->with('success', "Device set to “{$device['name']}”.");
    }

    /**
     * Re-pull the tenant's device list from Fullness.
     *
     * For when a device is added, renamed, or has users assigned in the CRM after
     * this screen was opened — without making the operator disconnect and sign in
     * again. The current selection survives the refresh.
     */
    public function refreshDevices(): RedirectResponse
    {
        $connection = FullnessConnection::current();
        if (! $connection || ! $connection->hasTenant()) {
            return back()->with('error', 'Choose a business first.');
        }

        $previousId = (string) ($connection->fullness_device_id ?? '');

        if ($error = $this->loadTenantDevices($connection, preserveSelection: true)) {
            return back()->with('error', $error);
        }

        $connection->refresh();

        $count = count($connection->devices ?? []);

        // The device that was selected is gone from Fullness. Say so plainly —
        // silently continuing with nothing selected invites a confusing "choose a
        // device first" error at fetch time.
        if ($previousId !== '' && $connection->fullness_device_id === null) {
            return back()->with('error', 'The device you had selected is no longer in Fullness. Choose another one.');
        }

        return back()->with('success', 'Device list refreshed — '.$count.' device'.($count === 1 ? '' : 's').'.');
    }

    public function fetch(ImportedUserValidator $validator): RedirectResponse
    {
        $connection = FullnessConnection::current();
        if (! $connection || ! $connection->isReady()) {
            return back()->with('error', 'Connect to Fullness and choose a business and device first.');
        }

        try {
            $users = $this->client->assignedUsers($connection);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($users === []) {
            return back()->with('error', 'No users are assigned to this device in Fullness.');
        }

        [$records, $validCount] = $this->buildRecords($users, $validator);

        $label = trim(($connection->tenant_name ?: $connection->tenant_id).' / '.($connection->fullness_device_name ?: 'device'), ' /');
        $batch = ImportBatch::create([
            'original_filename' => 'Fullness — '.$label,
            'total_rows' => count($records),
            'valid_rows' => $validCount,
            'invalid_rows' => count($records) - $validCount,
            'status' => ImportBatch::STATUS_PARSED,
        ]);

        $timestamp = now();
        foreach (array_chunk($records, 500) as $chunk) {
            $chunk = array_map(fn (array $record) => $record + [
                'import_batch_id' => $batch->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $chunk);

            ImportedUser::insert($chunk);
        }

        $connection->update(['last_synced_at' => now()]);

        return redirect()
            ->route('import.show', $batch)
            ->with('success', "Fetched {$validCount} of ".count($records).' users from Fullness.');
    }

    public function disconnect(): RedirectResponse
    {
        FullnessConnection::query()->delete();

        return redirect()->route('connectors.index')->with('success', 'Disconnected from Fullness.');
    }

    /**
     * Load the connection's selected-tenant devices from Fullness, store them,
     * and auto-select the device when the tenant has exactly one. Returns a
     * user-facing error message on failure, or null on success (including the
     * "no devices" case, which the UI handles).
     */
    private function loadTenantDevices(FullnessConnection $connection, bool $preserveSelection = false): ?string
    {
        try {
            $devices = $this->client->devices($connection);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        $currentId = $preserveSelection ? (string) ($connection->fullness_device_id ?? '') : '';

        $selected = $currentId !== ''
            // Refreshing: keep the operator's choice (picking up a rename or a new
            // assigned-user count). If it has vanished, select NOTHING rather than
            // falling back to auto-select — quietly pointing a sync at a different
            // terminal is worse than asking the operator to choose again.
            ? collect($devices)->first(fn ($d) => is_array($d) && (string) ($d['id'] ?? '') === $currentId)
            : (count($devices) === 1 ? $devices[0] : null);

        $connection->update([
            'devices' => $devices,
            'fullness_device_id' => isset($selected['id']) ? (string) $selected['id'] : null,
            'fullness_device_name' => $selected['name'] ?? null,
        ]);

        return null;
    }

    /**
     * Normalise the fetched users into insertable imported_user rows, applying
     * the same field validation + duplicate detection the spreadsheet import
     * uses so the two sources feed the device identically.
     *
     * @param  list<array<string, mixed>>  $users
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function buildRecords(array $users, ImportedUserValidator $validator): array
    {
        $records = [];
        $seen = [];
        $validCount = 0;

        foreach (array_values($users) as $index => $user) {
            $data = $validator->validate(
                isset($user['device_user_id']) ? (string) $user['device_user_id'] : null,
                isset($user['name']) ? (string) $user['name'] : null,
                isset($user['password']) ? (string) $user['password'] : null,
                isset($user['card_number']) ? (string) $user['card_number'] : null,
                isset($user['privilege']) ? (string) $user['privilege'] : null,
            );

            $errors = $data['errors'];
            if ($data['is_valid'] && $data['user_id'] !== '') {
                if (isset($seen[$data['user_id']])) {
                    $errors[] = 'Duplicate user id (row '.$seen[$data['user_id']].').';
                } else {
                    $seen[$data['user_id']] = $index + 1;
                }
            }

            $isValid = $errors === [];
            if ($isValid) {
                $validCount++;
            }

            $records[] = [
                'row_number' => $index + 1,
                'user_id' => $data['user_id'],
                'name' => $data['name'],
                'name_ascii' => $data['name_ascii'],
                'password' => $data['password'],
                'card_number' => $data['card_number'],
                'privilege' => $data['privilege'],
                'is_valid' => $isValid,
                'validation_errors' => $errors === [] ? null : json_encode($errors),
                'sync_status' => ImportedUser::SYNC_PENDING,
            ];
        }

        return [$records, $validCount];
    }

    private function deviceName(): string
    {
        return 'ZKTeco User Sync — '.(gethostname() ?: 'desktop');
    }
}
