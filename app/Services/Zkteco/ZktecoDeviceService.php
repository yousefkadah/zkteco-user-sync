<?php

declare(strict_types=1);

namespace App\Services\Zkteco;

use App\Models\Device;
use App\Models\ImportBatch;
use App\Models\ImportedUser;
use CodingLibs\ZktecoPhp\Libs\Services\Util;
use CodingLibs\ZktecoPhp\Libs\ZKTeco;
use Illuminate\Support\Collection;
use Throwable;

class ZktecoDeviceService
{
    public function __construct(private ZktecoConnectionFactory $factory) {}

    /**
     * Probe a device and return a small status payload for the UI.
     *
     * @return array{ok: bool, error?: string, serial?: ?string, name?: ?string, firmware?: ?string, users?: ?int}
     */
    public function testConnection(Device $device): array
    {
        $zk = $this->factory->make($device);

        try {
            if (! $zk->connect()) {
                return ['ok' => false, 'error' => 'Could not connect. Check the IP, port and that the device is on the same network.'];
            }

            $users = null;

            try {
                $users = count($zk->getUsers());
            } catch (Throwable) {
                // Reading users is best-effort for the status card.
            }

            return [
                'ok' => true,
                'serial' => $this->stringify($zk->serialNumber()),
                'name' => $this->stringify($zk->deviceName()),
                'firmware' => $this->stringify($zk->version()),
                'users' => $users,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            $this->safe(fn () => $zk->disconnect());
        }
    }

    /**
     * Read the users currently stored on the device.
     *
     * @return array{ok: bool, error?: string, users: list<array<string, mixed>>, count: int}
     */
    public function listUsers(Device $device): array
    {
        $zk = $this->factory->make($device);
        $ok = false;

        try {
            if (! $zk->connect()) {
                return ['ok' => false, 'error' => 'Could not connect. Check the IP, port and that the device is on this network.', 'users' => [], 'count' => 0];
            }

            $users = $this->normaliseDeviceUsers($zk->getUsers());
            $ok = true;

            return ['ok' => true, 'users' => $users, 'count' => count($users)];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'users' => [], 'count' => 0];
        } finally {
            $this->safe(fn () => $zk->disconnect());
            $device->update(['last_connected_at' => now(), 'last_connection_ok' => $ok]);
        }
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $raw
     * @return list<array<string, mixed>>
     */
    private function normaliseDeviceUsers(array $raw): array
    {
        $users = [];

        foreach ($raw as $row) {
            $role = (int) ($row['role'] ?? 0);
            $card = ltrim(trim((string) ($row['card_no'] ?? '')), '0');

            $users[] = [
                'uid' => (int) ($row['uid'] ?? 0),
                'user_id' => trim((string) ($row['user_id'] ?? '')),
                'name' => trim((string) ($row['name'] ?? '')),
                'role' => $role,
                'role_label' => match ($role) {
                    Util::LEVEL_ADMIN => 'Admin',
                    Util::LEVEL_USER => 'User',
                    default => 'Role '.$role,
                },
                'password' => trim((string) ($row['password'] ?? '')),
                'card_no' => $card !== '' ? $card : null,
            ];
        }

        usort($users, fn (array $a, array $b): int => $a['uid'] <=> $b['uid']);

        return $users;
    }

    /**
     * Push every valid user in the batch to the device, recording per-row results.
     */
    public function syncBatch(ImportBatch $batch, Device $device): void
    {
        $batch->users()->where('is_valid', false)->update([
            'sync_status' => ImportedUser::SYNC_SKIPPED,
        ]);

        /** @var Collection<int, ImportedUser> $users */
        $users = $batch->users()
            ->where('is_valid', true)
            ->orderBy('row_number')
            ->get();

        $zk = $this->factory->make($device);
        $synced = 0;
        $failed = 0;

        try {
            if (! $zk->connect()) {
                $users->each(fn (ImportedUser $user) => $this->markFailed($user, 'Could not connect to the device.'));
                $this->finish($batch, $device, 0, $users->count(), ImportBatch::STATUS_FAILED);

                return;
            }

            try {
                $existing = $zk->getUsers();
            } catch (Throwable $e) {
                $users->each(fn (ImportedUser $user) => $this->markFailed($user, 'Could not read the device user list: '.$e->getMessage()));
                $this->finish($batch, $device, 0, $users->count(), ImportBatch::STATUS_FAILED);

                return;
            }

            [$usedUids, $userIdToUid] = $this->indexExistingUsers($existing);

            $zk->disableDevice();
            $nextUid = 1;

            foreach ($users as $user) {
                $uid = $userIdToUid[$user->user_id] ?? null;

                if ($uid === null || $uid <= 0) {
                    while (isset($usedUids[$nextUid])) {
                        $nextUid++;
                    }
                    $uid = $nextUid;
                }

                $usedUids[$uid] = true;

                try {
                    $this->pushUser($zk, $user, $uid);
                    $user->update([
                        'device_uid' => $uid,
                        'sync_status' => ImportedUser::SYNC_SYNCED,
                        'sync_error' => null,
                        'synced_at' => now(),
                    ]);
                    $synced++;
                } catch (Throwable $e) {
                    $this->markFailed($user, $e->getMessage(), $uid);
                    $failed++;
                }

                $batch->update(['synced_count' => $synced, 'failed_count' => $failed]);
            }
        } finally {
            $this->safe(fn () => $zk->enableDevice());
            $this->safe(fn () => $zk->disconnect());
        }

        $status = $synced > 0 || $failed === 0
            ? ImportBatch::STATUS_COMPLETED
            : ImportBatch::STATUS_FAILED;

        $this->finish($batch, $device, $synced, $failed, $status);
    }

    private function pushUser(ZKTeco $zk, ImportedUser $user, int $uid): void
    {
        $role = $user->privilege === ImportedUser::PRIVILEGE_ADMIN
            ? Util::LEVEL_ADMIN
            : Util::LEVEL_USER;

        $result = $zk->setUser(
            $uid,
            $user->user_id,
            $user->name_ascii,
            (string) ($user->password ?? ''),
            $role,
            (int) ($user->card_number ?? 0),
        );

        if ($result === false) {
            throw new \RuntimeException('The device did not acknowledge the write.');
        }
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $existing
     * @return array{0: array<int, bool>, 1: array<string, int>}
     */
    private function indexExistingUsers(array $existing): array
    {
        $usedUids = [];
        $userIdToUid = [];

        foreach ($existing as $row) {
            $uid = (int) ($row['uid'] ?? 0);

            if ($uid > 0) {
                $usedUids[$uid] = true;
            }

            $userId = trim((string) ($row['user_id'] ?? ''));

            if ($userId !== '') {
                $userIdToUid[$userId] = $uid;
            }
        }

        return [$usedUids, $userIdToUid];
    }

    private function markFailed(ImportedUser $user, string $message, ?int $uid = null): void
    {
        $user->update([
            'device_uid' => $uid,
            'sync_status' => ImportedUser::SYNC_FAILED,
            'sync_error' => mb_strimwidth($message, 0, 250, '…'),
        ]);
    }

    private function finish(ImportBatch $batch, Device $device, int $synced, int $failed, string $status): void
    {
        $batch->update([
            'device_id' => $device->id,
            'status' => $status,
            'synced_count' => $synced,
            'failed_count' => $failed,
            'sync_finished_at' => now(),
        ]);

        $device->update([
            'last_connected_at' => now(),
            'last_connection_ok' => $status !== ImportBatch::STATUS_FAILED,
        ]);
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === false || $value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function safe(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable) {
            // Cleanup calls must never mask the real outcome.
        }
    }
}
