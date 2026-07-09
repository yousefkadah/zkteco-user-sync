<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'ip_address',
        'port',
        'comm_key',
        'serial_number',
        'last_connected_at',
        'last_connection_ok',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'comm_key' => 'integer',
            'last_connected_at' => 'datetime',
            'last_connection_ok' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ImportBatch, $this>
     */
    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }
}
