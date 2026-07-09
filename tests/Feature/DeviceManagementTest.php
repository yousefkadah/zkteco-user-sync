<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_device(): void
    {
        $this->post('/devices', [
            'name' => 'Front door',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
            'comm_key' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('devices', [
            'name' => 'Front door',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
        ]);
    }

    public function test_it_defaults_the_port_to_4370(): void
    {
        $this->post('/devices', [
            'name' => 'No port',
            'ip_address' => '10.0.0.5',
        ])->assertRedirect();

        $this->assertDatabaseHas('devices', [
            'ip_address' => '10.0.0.5',
            'port' => 4370,
        ]);
    }

    public function test_it_rejects_an_invalid_ip(): void
    {
        $this->post('/devices', [
            'name' => 'Bad',
            'ip_address' => 'not-an-ip',
        ])->assertSessionHasErrors('ip_address');

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_it_updates_a_device(): void
    {
        $device = Device::create(['name' => 'Old', 'ip_address' => '10.0.0.1', 'port' => 4370]);

        $this->put("/devices/{$device->id}", [
            'name' => 'New name',
            'ip_address' => '10.0.0.2',
            'port' => 4371,
        ])->assertRedirect();

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'name' => 'New name',
            'ip_address' => '10.0.0.2',
            'port' => 4371,
        ]);
    }

    public function test_it_deletes_a_device(): void
    {
        $device = Device::create(['name' => 'Temp', 'ip_address' => '10.0.0.9', 'port' => 4370]);

        $this->delete("/devices/{$device->id}")->assertRedirect();

        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    }
}
