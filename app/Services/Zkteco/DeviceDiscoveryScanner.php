<?php

declare(strict_types=1);

namespace App\Services\Zkteco;

use App\Models\Device;
use App\Support\Ipv4Range;
use CodingLibs\ZktecoPhp\Libs\Services\Util;
use Throwable;

class DeviceDiscoveryScanner
{
    /**
     * Hard ceiling on the number of host addresses probed in one scan, across
     * all local subnets, so a large network can't produce a huge sweep.
     */
    private const MAX_HOSTS = 1024;

    public function __construct(
        private LocalNetworkResolver $resolver,
        private ZktecoConnectionFactory $factory,
    ) {}

    /**
     * Sweep the local network(s) for ZKTeco terminals and return each responder
     * enriched with its serial / name / firmware where readable.
     *
     * @return list<array{ip: string, serial: ?string, name: ?string, firmware: ?string}>
     */
    public function scan(int $port = 4370, float $waitSeconds = 1.5): array
    {
        $hosts = [];

        foreach ($this->resolver->interfaces() as $interface) {
            foreach (Ipv4Range::hosts($interface['ip'], $interface['prefix']) as $host) {
                $hosts[$host] = true;

                if (count($hosts) >= self::MAX_HOSTS) {
                    break 2;
                }
            }
        }

        if ($hosts === []) {
            return [];
        }

        $devices = [];

        foreach ($this->sweep(array_keys($hosts), $port, $waitSeconds) as $ip) {
            $devices[] = ['ip' => $ip, ...$this->probeInfo($ip, $port)];
        }

        usort($devices, fn (array $a, array $b): int => ip2long($a['ip']) <=> ip2long($b['ip']));

        return $devices;
    }

    /**
     * Blast a CMD_CONNECT probe at every host at once, then collect the ZKTeco
     * acknowledgements that come back within the wait window.
     *
     * @param  list<string>  $hosts
     * @return list<string>
     */
    private function sweep(array $hosts, int $port, float $waitSeconds): array
    {
        $packet = Util::createHeader(Util::CMD_CONNECT, 0, 0, Util::USHRT_MAX - 1, '');

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($socket === false) {
            return [];
        }

        socket_set_nonblock($socket);

        foreach ($hosts as $ip) {
            @socket_sendto($socket, $packet, strlen($packet), 0, $ip, $port);
        }

        $found = [];
        $deadline = microtime(true) + $waitSeconds;

        while (microtime(true) < $deadline) {
            $buffer = '';
            $from = '';
            $fromPort = 0;

            $received = @socket_recvfrom($socket, $buffer, 1024, 0, $from, $fromPort);

            if ($received !== false && $received >= 8 && $from !== '' && Util::checkValid($buffer) !== false) {
                $found[$from] = true;
            } else {
                usleep(3000);
            }
        }

        socket_close($socket);

        return array_keys($found);
    }

    /**
     * @return array{serial: ?string, name: ?string, firmware: ?string}
     */
    private function probeInfo(string $ip, int $port): array
    {
        $empty = ['serial' => null, 'name' => null, 'firmware' => null];

        try {
            $zk = $this->factory->make(new Device(['ip_address' => $ip, 'port' => $port]));

            if (! $zk->connect()) {
                return $empty;
            }

            $info = [
                'serial' => $this->stringify($zk->serialNumber()),
                'name' => $this->stringify($zk->deviceName()),
                'firmware' => $this->stringify($zk->version()),
            ];

            $zk->disconnect();

            return $info;
        } catch (Throwable) {
            return $empty;
        }
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === false || $value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
