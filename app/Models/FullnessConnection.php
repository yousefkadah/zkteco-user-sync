<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single local Fullness CRM connection (base URL + encrypted Sanctum token
 * + selected tenant). This app connects to at most one Fullness account, so the
 * table is used as a singleton via {@see self::current()}.
 */
class FullnessConnection extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'base_url',
        'token',
        'tenants',
        'tenant_id',
        'tenant_name',
        'devices',
        'fullness_device_id',
        'fullness_device_name',
        'owner_email',
        'last_connected_at',
        'last_synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'tenants' => 'array',
            'devices' => 'array',
            'last_connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * The current (single) connection row, or null when not connected.
     */
    public static function current(): ?self
    {
        return self::query()->latest('id')->first();
    }

    /**
     * True when a token is present (the owner has logged in).
     */
    public function isConnected(): bool
    {
        return filled($this->token);
    }

    /**
     * True when a tenant (business) has been selected.
     */
    public function hasTenant(): bool
    {
        return $this->isConnected() && filled($this->tenant_id);
    }

    /**
     * True when a tenant AND a device are selected — ready to fetch + sync.
     */
    public function isReady(): bool
    {
        return $this->hasTenant() && filled($this->fullness_device_id);
    }
}
