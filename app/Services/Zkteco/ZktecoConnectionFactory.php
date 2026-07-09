<?php

declare(strict_types=1);

namespace App\Services\Zkteco;

use App\Models\Device;
use CodingLibs\ZktecoPhp\Libs\ZKTeco;

/**
 * Builds configured {@see ZKTeco} connections. Isolated behind a factory so the
 * device I/O can be swapped for a fake in tests (no hardware required).
 */
class ZktecoConnectionFactory
{
    public function make(Device $device): ZKTeco
    {
        return new ZKTeco(
            $device->ip_address,
            $device->port ?: 4370,
            shouldPing: false,
            timeout: 25,
            password: $device->comm_key ?? 0,
        );
    }
}
