<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Services\Zkteco\DeviceDiscoveryScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class DeviceDiscoveryScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_returns_discovered_devices_and_marks_known_ones(): void
    {
        Device::create(['name' => 'Existing', 'ip_address' => '192.168.1.201', 'port' => 4370]);

        $this->mock(DeviceDiscoveryScanner::class, function (MockInterface $mock) {
            $mock->shouldReceive('scan')->once()->andReturn([
                ['ip' => '192.168.1.201', 'serial' => 'AAA', 'name' => 'Front Door', 'firmware' => '1.0'],
                ['ip' => '192.168.1.55', 'serial' => 'BBB', 'name' => null, 'firmware' => null],
            ]);
        });

        $response = $this->getJson('/devices/scan');

        $response->assertOk();
        $response->assertJsonCount(2, 'devices');

        // Already-registered device is flagged and keeps its device-reported name.
        $response->assertJsonPath('devices.0.ip_address', '192.168.1.201');
        $response->assertJsonPath('devices.0.already_added', true);
        $response->assertJsonPath('devices.0.suggested_name', 'Front Door');

        // New device: no name, so the suggestion falls back to the serial.
        $response->assertJsonPath('devices.1.already_added', false);
        $response->assertJsonPath('devices.1.suggested_name', 'ZKTeco BBB');
    }
}
