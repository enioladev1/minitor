<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $enabled
 * @property string|null $s3_endpoint
 * @property string $s3_region
 * @property string|null $s3_bucket
 * @property string|null $s3_access_key_id
 * @property string|null $s3_secret_access_key
 * @property string|null $s3_path_prefix
 * @property int $retention_count
 * @property Carbon|null $last_run_at
 * @property string|null $last_status
 * @property string|null $last_error
 */
class BackupSetting extends Model
{
    protected $fillable = [
        'enabled',
        's3_endpoint',
        's3_region',
        's3_bucket',
        's3_access_key_id',
        's3_secret_access_key',
        's3_path_prefix',
        'retention_count',
    ];

    protected $hidden = [
        's3_access_key_id',
        's3_secret_access_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            's3_access_key_id' => 'encrypted',
            's3_secret_access_key' => 'encrypted',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * There is exactly one row: this app is single-admin/instance-wide, so
     * backup config isn't scoped per-project or per-user.
     *
     * Passes explicit defaults rather than relying on `firstOrCreate([])`
     * alone — Eloquent doesn't re-read a fresh row after a bare insert, so
     * the in-memory model would otherwise have null for every column that
     * only has a DB-level default (enabled, s3_region, retention_count,
     * etc.), even though the row itself is correct.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'enabled' => false,
            's3_region' => 'auto',
            'retention_count' => 7,
        ]);
    }

    public function isConfigured(): bool
    {
        return filled($this->s3_bucket)
            && filled($this->s3_access_key_id)
            && filled($this->s3_secret_access_key)
            && filled($this->s3_endpoint);
    }
}
