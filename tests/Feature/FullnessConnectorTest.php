<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FullnessConnection;
use App\Models\ImportBatch;
use App\Services\Fullness\FullnessClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FullnessConnectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_stores_the_connection_and_auto_selects_a_single_business(): void
    {
        $client = Mockery::mock(FullnessClient::class);
        $client->shouldReceive('login')
            ->once()
            ->andReturn([
                'token' => '1|secret-token',
                'tenants' => [
                    ['id' => 't1', 'name' => 'Biz One', 'role' => 'owner', 'role_id' => 2, 'logo_url' => null],
                ],
                'owner_email' => 'owner@biz.co.il',
            ]);
        // The single business is auto-selected, so its devices are loaded too.
        $client->shouldReceive('devices')->once()->andReturn([
            ['id' => 5, 'name' => 'Front Door', 'assigned_count' => 3],
        ]);
        $this->instance(FullnessClient::class, $client);

        $this->post('/connectors/connect', [
            'base_url' => 'https://fullness.co.il',
            'email' => 'owner@biz.co.il',
            'password' => 'secret',
        ])->assertRedirect();

        $this->assertDatabaseCount('fullness_connections', 1);

        $connection = FullnessConnection::current();
        $this->assertNotNull($connection);
        $this->assertSame('t1', $connection->tenant_id);
        $this->assertSame('Biz One', $connection->tenant_name);
        $this->assertSame('1|secret-token', $connection->token); // decrypted via cast
        $this->assertSame('5', $connection->fullness_device_id); // single device auto-selected
        $this->assertSame('Front Door', $connection->fullness_device_name);
        $this->assertTrue($connection->isReady());
    }

    public function test_connect_does_not_auto_select_when_multiple_businesses_exist(): void
    {
        $client = Mockery::mock(FullnessClient::class);
        $client->shouldReceive('login')->once()->andReturn([
            'token' => '1|secret-token',
            'tenants' => [
                ['id' => 't1', 'name' => 'Biz One', 'role' => 'owner', 'role_id' => 2, 'logo_url' => null],
                ['id' => 't2', 'name' => 'Biz Two', 'role' => 'owner', 'role_id' => 2, 'logo_url' => null],
            ],
            'owner_email' => 'owner@biz.co.il',
        ]);
        $this->instance(FullnessClient::class, $client);

        $this->post('/connectors/connect', [
            'base_url' => 'https://fullness.co.il',
            'email' => 'owner@biz.co.il',
            'password' => 'secret',
        ])->assertRedirect();

        $connection = FullnessConnection::current();
        $this->assertNull($connection->tenant_id);
        $this->assertCount(2, $connection->tenants);
    }

    public function test_connect_surfaces_a_login_error_without_saving(): void
    {
        $client = Mockery::mock(FullnessClient::class);
        $client->shouldReceive('login')->once()->andThrow(new \RuntimeException('Login failed.'));
        $this->instance(FullnessClient::class, $client);

        $this->post('/connectors/connect', [
            'base_url' => 'https://fullness.co.il',
            'email' => 'owner@biz.co.il',
            'password' => 'wrong',
        ])->assertRedirect()->assertSessionHas('error', 'Login failed.');

        $this->assertDatabaseCount('fullness_connections', 0);
    }

    public function test_fetch_creates_an_import_batch_from_assigned_users(): void
    {
        FullnessConnection::create([
            'base_url' => 'https://fullness.co.il',
            'token' => '1|secret-token',
            'tenants' => [['id' => 't1', 'name' => 'Biz One']],
            'tenant_id' => 't1',
            'tenant_name' => 'Biz One',
            'devices' => [['id' => 1, 'name' => 'Front Door']],
            'fullness_device_id' => '1',
            'fullness_device_name' => 'Front Door',
            'owner_email' => 'owner@biz.co.il',
        ]);

        $client = Mockery::mock(FullnessClient::class);
        $client->shouldReceive('assignedUsers')
            ->once()
            ->andReturn([
                ['device_id' => 1, 'device_user_id' => 42, 'name' => 'יוסי לוי', 'name_ascii' => 'Yosi_Levi', 'privilege' => 0, 'password' => '038194', 'card_number' => null],
                ['device_id' => 1, 'device_user_id' => 43, 'name' => 'Dana', 'name_ascii' => 'Dana', 'privilege' => 0, 'password' => '112233', 'card_number' => null],
            ]);
        $this->instance(FullnessClient::class, $client);

        $batch = null;
        $this->post('/connectors/fetch')->assertRedirect();

        $this->assertDatabaseCount('import_batches', 1);
        $batch = ImportBatch::first();
        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(2, $batch->valid_rows);
        $this->assertStringContainsString('Biz One', $batch->original_filename);

        $this->assertDatabaseCount('imported_users', 2);
        $this->assertDatabaseHas('imported_users', [
            'user_id' => '42',
            'password' => '038194',
            'privilege' => 'user',
            'is_valid' => true,
        ]);

        $this->assertNotNull(FullnessConnection::current()->last_synced_at);
    }

    public function test_fetch_requires_a_selected_business(): void
    {
        $this->post('/connectors/fetch')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('import_batches', 0);
    }

    public function test_select_tenant_loads_devices_and_auto_selects_a_single_device(): void
    {
        FullnessConnection::create([
            'base_url' => 'https://fullness.co.il',
            'token' => '1|secret-token',
            'tenants' => [['id' => 't1', 'name' => 'Biz One'], ['id' => 't2', 'name' => 'Biz Two']],
            'owner_email' => 'owner@biz.co.il',
        ]);

        $client = Mockery::mock(FullnessClient::class);
        $client->shouldReceive('devices')->once()->andReturn([
            ['id' => 9, 'name' => 'Only Device', 'assigned_count' => 4],
        ]);
        $this->instance(FullnessClient::class, $client);

        $this->post('/connectors/tenant', ['tenant_id' => 't1'])->assertRedirect();

        $connection = FullnessConnection::current();
        $this->assertSame('t1', $connection->tenant_id);
        $this->assertCount(1, $connection->devices);
        $this->assertSame('9', $connection->fullness_device_id); // auto-selected
        $this->assertTrue($connection->isReady());
    }

    public function test_select_tenant_lists_multiple_devices_without_auto_selecting(): void
    {
        FullnessConnection::create([
            'base_url' => 'https://fullness.co.il',
            'token' => '1|secret-token',
            'tenants' => [['id' => 't1', 'name' => 'Biz One']],
        ]);

        $client = Mockery::mock(FullnessClient::class);
        $client->shouldReceive('devices')->once()->andReturn([
            ['id' => 1, 'name' => 'Sakhnin', 'assigned_count' => 100],
            ['id' => 2, 'name' => 'Kafr Manda', 'assigned_count' => 80],
        ]);
        $this->instance(FullnessClient::class, $client);

        $this->post('/connectors/tenant', ['tenant_id' => 't1'])->assertRedirect();

        $connection = FullnessConnection::current();
        $this->assertCount(2, $connection->devices);
        $this->assertNull($connection->fullness_device_id); // must choose one
        $this->assertTrue($connection->hasTenant());
        $this->assertFalse($connection->isReady());
    }

    public function test_select_device_sets_the_chosen_device(): void
    {
        FullnessConnection::create([
            'base_url' => 'https://fullness.co.il',
            'token' => '1|secret-token',
            'tenants' => [['id' => 't1', 'name' => 'Biz One']],
            'tenant_id' => 't1',
            'tenant_name' => 'Biz One',
            'devices' => [
                ['id' => 1, 'name' => 'Sakhnin'],
                ['id' => 2, 'name' => 'Kafr Manda'],
            ],
        ]);

        $this->post('/connectors/device', ['device_id' => 2])->assertRedirect();

        $connection = FullnessConnection::current();
        $this->assertSame('2', $connection->fullness_device_id);
        $this->assertSame('Kafr Manda', $connection->fullness_device_name);
        $this->assertTrue($connection->isReady());
    }

    public function test_disconnect_removes_the_connection(): void
    {
        FullnessConnection::create([
            'base_url' => 'https://fullness.co.il',
            'token' => '1|secret-token',
            'tenant_id' => 't1',
            'tenant_name' => 'Biz One',
        ]);

        $this->delete('/connectors')->assertRedirect();

        $this->assertDatabaseCount('fullness_connections', 0);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
