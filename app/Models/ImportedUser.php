<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportedUser extends Model
{
    public const SYNC_PENDING = 'pending';

    public const SYNC_SYNCED = 'synced';

    public const SYNC_FAILED = 'failed';

    public const SYNC_SKIPPED = 'skipped';

    public const PRIVILEGE_USER = 'user';

    public const PRIVILEGE_ADMIN = 'admin';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'import_batch_id',
        'row_number',
        'device_uid',
        'user_id',
        'name',
        'name_ascii',
        'password',
        'card_number',
        'privilege',
        'is_valid',
        'validation_errors',
        'sync_status',
        'sync_error',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'device_uid' => 'integer',
            'is_valid' => 'boolean',
            'validation_errors' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
