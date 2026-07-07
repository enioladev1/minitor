<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CacheEvent extends Model
{
    protected $table = 'cache_events';

    public $timestamps = false;

    protected $fillable = [
        'project_id', 'store', 'key', 'type', 'duration_us', 'ttl', 'trace_id', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
