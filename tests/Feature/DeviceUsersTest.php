<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Services\Zkteco\ZktecoConnectionFactory;
use App\Services\Zkteco\ZktecoDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Doubles\FakeZkteco;
use Tests\Doubles\FakeZktecoConnectionFactory;
use Tests\TestCase;

class DeviceUsersTest extends TestCase
{
    use RefreshDatabase;

    private function bindFake(FakeZkteco $fake): ZktecoDeviceService
    {
        $this->app->instance(ZktecoConnectionFactory::class, new FakeZktecoConnectionFactory($fake));

        return $this->app->make(ZktecoDeviceService::class);
    }

    public function test_it_lists_and_normalises_device_users(): void
    {
        $device = Device::create(['name' => 'D', 'ip_address' => '192.168.1.201', 'port' => 4370]);

        $fake = new FakeZkteco;
        $fake->existingUsers = [
            ['uid' => 2, 'user_id' => '1001', 'name' => 'Alice', 'role' => 0, 'password' => '1234', 'card_no' => '00000012345'],
            ['uid' => 1, 'user_id' => '1', 'name' => 'Admin', 'role' => 14, 'password' => '', 'card_no' => '00000000000'],
        ];

        $result = $this->bindFake($fake)->listUsers($device);

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['count']);

        // Sorted by device slot (uid): admin (uid 1) first.
        $this->assertSame(1, $result['users'][0]['uid']);
        $this->assertSame('Admin', $result['users'][0]['role_label']);
        $this->assertNull($result['users'][0]['card_no']); // all-zero card -> none

        $this->assertSame('Alice', $result['users'][1]['name']);
        $this->assertSame('User', $result['users'][1]['role_label']);
        $this->assertSame('12345', $result['users'][1]['card_no']); // leading zeros stripped

        $this->assertTrue($device->fresh()->last_connection_ok);
    }

    public function test_it_reports_an_error_when_the_device_is_unreachable(): void
    {
        $device = Device::create(['name' => 'D', 'ip_address' => '192.168.1.201', 'port' => 4370]);

        $fake = new FakeZkteco;
        $fake->connectResult = false;

        $result = $this->bindFake($fake)->listUsers($device);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, $result['count']);
        $this->assertFalse($device->fresh()->last_connection_ok);
    }

    public function test_it_sanitises_raw_socket_errors_from_an_offline_device(): void
    {
        $device = Device::create(['name' => 'D', 'ip_address' => '192.168.1.69', 'port' => 4370]);

        $fake = new FakeZkteco;
        // A real unreachable device throws a raw socket error rather than returning false.
        $fake->throwMessage = 'socket_sendto(): Unable to write to socket [65]: No route to host';

        $result = $this->bindFake($fake)->listUsers($device);

        $this->assertFalse($result['ok']);
        // The scary raw socket error must never reach the user.
        $this->assertStringNotContainsStringIgnoringCase('socket_sendto', $result['error']);
        $this->assertStringNotContainsStringIgnoringCase('No route to host', $result['error']);
        $this->assertStringContainsStringIgnoringCase('could not reach the device', $result['error']);
        $this->assertFalse($device->fresh()->last_connection_ok);
    }

    public function test_the_users_page_renders_with_device_users(): void
    {
        $device = Device::create(['name' => 'Front Door', 'ip_address' => '192.168.1.201', 'port' => 4370]);

        $fake = new FakeZkteco;
        $fake->existingUsers = [
            ['uid' => 1, 'user_id' => '1001', 'name' => 'Alice', 'role' => 0, 'password' => '1234', 'card_no' => '00000000000'],
        ];
        $this->app->instance(ZktecoConnectionFactory::class, new FakeZktecoConnectionFactory($fake));

        $this->get("/devices/{$device->id}/users")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Devices/Users')
                ->where('device.name', 'Front Door')
                ->where('result.ok', true)
                ->where('result.count', 1)
                ->where('result.users.0.name', 'Alice'));
    }
}
