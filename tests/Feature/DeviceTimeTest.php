<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Services\Zkteco\ZktecoConnectionFactory;
use App\Services\Zkteco\ZktecoDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Doubles\FakeZkteco;
use Tests\Doubles\FakeZktecoConnectionFactory;
use Tests\TestCase;

/**
 * Reading and setting the terminal clock.
 *
 * A ZKTeco terminal stops keeping its own time once it talks to an ADMS server:
 * it derives the clock from the server's HTTP `Date:` header (GMT) plus whatever
 * offset the server advertises. If the server advertises none, the device applies
 * 0 and sits on UTC — recording every punch hours off (3h in an Israeli summer)
 * with nothing on screen to say so. These cover surfacing that drift and setting
 * the clock back over UDP, which works whether or not the device uses ADMS.
 */
class DeviceTimeTest extends TestCase
{
    use RefreshDatabase;

    private function bindFake(FakeZkteco $fake): ZktecoDeviceService
    {
        $this->app->instance(ZktecoConnectionFactory::class, new FakeZktecoConnectionFactory($fake));

        return $this->app->make(ZktecoDeviceService::class);
    }

    private function device(): Device
    {
        return Device::create(['name' => 'Front Door', 'ip_address' => '192.168.1.201', 'port' => 4370]);
    }

    public function test_it_reports_a_device_sitting_on_utc_as_drifted(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00'); // Israel local (IDT, UTC+3)

        $fake = new FakeZkteco;
        $fake->deviceTime = '2026-07-15 09:00:00'; // device stuck on UTC — 3h behind

        $result = $this->bindFake($fake)->readTime($this->device());

        $this->assertTrue($result['ok']);
        $this->assertSame('2026-07-15 09:00:00', $result['device_time']);
        // Negative = device behind this machine.
        $this->assertSame(-10800, $result['drift_seconds']);

        Carbon::setTestNow();
    }

    public function test_a_correct_device_reports_no_drift(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');

        $fake = new FakeZkteco;
        $fake->deviceTime = '2026-07-15 12:00:00';

        $result = $this->bindFake($fake)->readTime($this->device());

        $this->assertSame(0, $result['drift_seconds']);

        Carbon::setTestNow();
    }

    public function test_it_sets_the_device_clock_to_local_time_not_utc(): void
    {
        // The device stores wall-clock with no offset, so we must send local time.
        // Sending UTC here would recreate the very bug this feature fixes.
        Carbon::setTestNow('2026-07-15 12:00:00');

        $fake = new FakeZkteco;
        $fake->deviceTime = '2026-07-15 09:00:00';

        $result = $this->bindFake($fake)->syncTime($this->device());

        $this->assertTrue($result['ok']);
        $this->assertSame('2026-07-15 12:00:00', $fake->deviceTime);
        $this->assertSame('2026-07-15 12:00:00', $result['device_time']);

        Carbon::setTestNow();
    }

    public function test_it_reports_failure_when_the_device_refuses_the_write(): void
    {
        $fake = new FakeZkteco;
        $fake->acceptTimeWrite = false;

        $result = $this->bindFake($fake)->syncTime($this->device());

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('refused', $result['error']);
    }

    public function test_it_reports_a_connection_failure_rather_than_pretending(): void
    {
        $fake = new FakeZkteco;
        $fake->connectResult = false;

        $result = $this->bindFake($fake)->readTime($this->device());

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Could not connect', $result['error']);
    }

    public function test_it_handles_an_unreadable_device_clock(): void
    {
        $fake = new FakeZkteco;
        $fake->deviceTime = null; // getTime() returns false

        $result = $this->bindFake($fake)->readTime($this->device());

        $this->assertTrue($result['ok']);
        $this->assertNull($result['device_time']);
        $this->assertNull($result['drift_seconds']);
    }

    public function test_the_endpoint_returns_the_drift(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');

        $fake = new FakeZkteco;
        $fake->deviceTime = '2026-07-15 09:00:00';
        $this->bindFake($fake);

        $device = $this->device();

        $this->getJson("/devices/{$device->id}/time")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('drift_seconds', -10800);

        Carbon::setTestNow();
    }

    public function test_the_sync_endpoint_sets_the_clock(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');

        $fake = new FakeZkteco;
        $fake->deviceTime = '2026-07-15 09:00:00';
        $this->bindFake($fake);

        $device = $this->device();

        $this->post("/devices/{$device->id}/time")->assertRedirect();

        $this->assertSame('2026-07-15 12:00:00', $fake->deviceTime);

        Carbon::setTestNow();
    }
}
