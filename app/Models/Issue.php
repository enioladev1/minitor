<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    public const STATUSES = ['open', 'resolved', 'ignored'];

    protected $fillable = [
        'project_id', 'fingerprint', 'class', 'file', 'line',
        'last_message', 'occurrences_count', 'status', 'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(MinitorException::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(IssueEvent::class);
    }
}
