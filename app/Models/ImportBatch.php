<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    public const STATUS_PARSED = 'parsed';

    public const STATUS_SYNCING = 'syncing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'original_filename',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'status',
        'synced_count',
        'failed_count',
        'sync_started_at',
        'sync_finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
            'synced_count' => 'integer',
            'failed_count' => 'integer',
            'sync_started_at' => 'datetime',
            'sync_finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return HasMany<ImportedUser, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(ImportedUser::class);
    }

    public function isSyncing(): bool
    {
        return $this->status === self::STATUS_SYNCING;
    }
}
