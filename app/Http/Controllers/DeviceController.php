<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Device;
use App\Services\Zkteco\ZktecoDeviceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Devices/Index', [
            'devices' => Device::orderBy('name')->get()->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'ip_address' => $device->ip_address,
                'port' => $device->port,
                'comm_key' => $device->comm_key,
                'serial_number' => $device->serial_number,
                'notes' => $device->notes,
                'last_connection_ok' => $device->last_connection_ok,
                'last_connected_at' => $device->last_connected_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        Device::create([
            'name' => $request->string('name')->toString(),
            'ip_address' => $request->string('ip_address')->toString(),
            'port' => $request->integer('port') ?: 4370,
            'comm_key' => $request->filled('comm_key') ? $request->integer('comm_key') : null,
            'notes' => $request->input('notes'),
        ]);

        return back()->with('success', 'Device added.');
    }

    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse
    {
        $device->update([
            'name' => $request->string('name')->toString(),
            'ip_address' => $request->string('ip_address')->toString(),
            'port' => $request->integer('port') ?: 4370,
            'comm_key' => $request->filled('comm_key') ? $request->integer('comm_key') : null,
            'notes' => $request->input('notes'),
        ]);

        return back()->with('success', 'Device updated.');
    }

    public function destroy(Device $device): RedirectResponse
    {
        $device->delete();

        return back()->with('success', 'Device removed.');
    }

    public function test(Device $device, ZktecoDeviceService $service): RedirectResponse
    {
        $result = $service->testConnection($device);

        $device->update([
            'serial_number' => $result['serial'] ?? $device->serial_number,
            'last_connected_at' => now(),
            'last_connection_ok' => $result['ok'],
        ]);

        if ($result['ok']) {
            $details = array_filter([
                $result['name'] ?? null,
                isset($result['serial']) ? 'S/N '.$result['serial'] : null,
                isset($result['users']) ? $result['users'].' users on device' : null,
            ]);

            return back()->with('success', 'Connected. '.implode(' · ', $details));
        }

        return back()->with('error', 'Connection failed: '.($result['error'] ?? 'unknown error'));
    }
}
