<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Small helpers for turning a local IPv4 address + subnet into the list of host
 * addresses to probe during device discovery.
 */
final class Ipv4Range
{
    /**
     * Number of hosts a single scan will ever enumerate. Anything wider than
     * this (e.g. a /16) is narrowed to the block around the given IP so a scan
     * stays fast.
     */
    public const DEFAULT_CAP = 512;

    public static function prefixFromNetmask(string $netmask): ?int
    {
        $long = ip2long($netmask);

        if ($long === false) {
            return null;
        }

        $bits = sprintf('%032b', $long & 0xFFFFFFFF);

        // A valid netmask is a run of 1s followed by a run of 0s.
        if (! preg_match('/^1*0*$/', $bits)) {
            return null;
        }

        return substr_count($bits, '1');
    }

    /**
     * Usable host addresses in the subnet that contains $ip at $prefix, excluding
     * the network address, the broadcast address and $ip itself. If the subnet is
     * wider than $cap hosts, the prefix is narrowed around $ip to stay under $cap.
     *
     * @return list<string>
     */
    public static function hosts(string $ip, int $prefix, int $cap = self::DEFAULT_CAP): array
    {
        $ipLong = ip2long($ip);

        if ($ipLong === false) {
            return [];
        }

        $ipLong &= 0xFFFFFFFF;
        $prefix = max(0, min(32, $prefix));

        while ((32 - $prefix) > 0 && ((2 ** (32 - $prefix)) - 2) > $cap) {
            $prefix++;
        }

        if ($prefix >= 31) {
            return [long2ip($ipLong)];
        }

        $mask = (~((1 << (32 - $prefix)) - 1)) & 0xFFFFFFFF;
        $network = $ipLong & $mask;
        $broadcast = $network | (~$mask & 0xFFFFFFFF);

        $hosts = [];

        for ($host = $network + 1; $host < $broadcast; $host++) {
            if ($host === $ipLong) {
                continue;
            }

            $hosts[] = long2ip($host);
        }

        return $hosts;
    }
}
