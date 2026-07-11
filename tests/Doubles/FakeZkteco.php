<?php

declare(strict_types=1);

namespace Tests\Doubles;

use CodingLibs\ZktecoPhp\Libs\ZKTeco;

/**
 * In-memory stand-in for a physical ZKTeco terminal. Records the users that
 * would be pushed and lets a test simulate connection / per-user failures,
 * so the sync pipeline can be exercised without any hardware or network.
 */
class FakeZkteco extends ZKTeco
{
    public bool $connectResult = true;

    /** When set, connect() throws with this message (simulates a raw socket error). */
    public ?string $throwMessage = null;

    /** @var array<int, array<string, mixed>> */
    public array $existingUsers = [];

    /** @var list<array<string, mixed>> */
    public array $pushed = [];

    /** @var list<string> user_ids that should be rejected by the device */
    public array $failUserIds = [];

    /** @var list<int> device slots (uid) that were removed */
    public array $removed = [];

    public bool $cleared = false;

    public bool $disabled = false;

    public function __construct()
    {
        // Intentionally does not call parent::__construct — no real UDP socket.
    }

    public function connect(): bool
    {
        if ($this->throwMessage !== null) {
            throw new \RuntimeException($this->throwMessage);
        }

        return $this->connectResult;
    }

    public function disconnect(): bool
    {
        return true;
    }

    public function getUsers(?callable $callback = null): array
    {
        return $this->existingUsers;
    }

    public function disableDevice()
    {
        $this->disabled = true;

        return true;
    }

    public function enableDevice()
    {
        $this->disabled = false;

        return true;
    }

    public function setUser(int $uid, $userid, string $name, $password, int $role = 0, int $cardno = 0)
    {
        $this->pushed[] = compact('uid', 'userid', 'name', 'password', 'role', 'cardno');

        if (in_array((string) $userid, $this->failUserIds, true)) {
            return false;
        }

        return 'ACK';
    }

    public function removeUser(int $uid)
    {
        $this->removed[] = $uid;

        return true;
    }

    public function clearAllUsers()
    {
        $this->cleared = true;
        $this->existingUsers = [];

        return true;
    }

    public function serialNumber()
    {
        return 'FAKE-SN-001';
    }

    public function deviceName()
    {
        return 'Fake Terminal';
    }

    public function version()
    {
        return '1.0.0';
    }
}
