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
     * True when a tenant has been selected and the app is ready to sync.
     */
    public function isReady(): bool
    {
        return $this->isConnected() && filled($this->tenant_id);
    }
}
