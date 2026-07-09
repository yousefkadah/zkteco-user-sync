<?php

declare(strict_types=1);

namespace App\Services\Zkteco;

use App\Support\Ipv4Range;

/**
 * Discovers the machine's own IPv4 subnet(s) so the scanner knows which host
 * addresses to probe. Uses net_get_interfaces() when available, and always
 * falls back to the primary routed IP.
 */
class LocalNetworkResolver
{
    /**
     * @return list<array{ip: string, prefix: int}>
     */
    public function interfaces(): array
    {
        $result = [];

        if (function_exists('net_get_interfaces')) {
            foreach (@net_get_interfaces() ?: [] as $interface) {
                foreach ($interface['unicast'] ?? [] as $unicast) {
                    if (($unicast['family'] ?? null) !== AF_INET) {
                        continue;
                    }

                    $ip = $unicast['address'] ?? null;

                    if (! is_string($ip) || $this->isIgnorable($ip)) {
                        continue;
                    }

                    $netmask = $unicast['netmask'] ?? null;
                    $prefix = is_string($netmask) ? (Ipv4Range::prefixFromNetmask($netmask) ?? 24) : 24;

                    $result[$ip] = ['ip' => $ip, 'prefix' => $prefix];
                }
            }
        }

        $primary = $this->primaryIp();

        if ($primary !== null && ! isset($result[$primary])) {
            $result[$primary] = ['ip' => $primary, 'prefix' => 24];
        }

        return array_values($result);
    }

    /**
     * The local IP the OS would use to reach the internet. Opening a UDP socket
     * to a public address does not send any traffic; it just resolves the route
     * so we can read back the chosen source address.
     */
    public function primaryIp(): ?string
    {
        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($socket === false) {
            return null;
        }

        @socket_connect($socket, '8.8.8.8', 53);
        $ok = @socket_getsockname($socket, $address);
        @socket_close($socket);

        return $ok && is_string($address) && ! $this->isIgnorable($address) ? $address : null;
    }

    private function isIgnorable(string $ip): bool
    {
        return $ip === '0.0.0.0'
            || str_starts_with($ip, '127.')
            || str_starts_with($ip, '169.254.');
    }
}
