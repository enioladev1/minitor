<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobAttempt extends Model
{
    protected $table = 'job_attempts';

    public $timestamps = false;

    protected $fillable = [
        'project_id', 'job_id', 'attempt_id', 'attempt', 'name', 'connection',
        'queue', 'status', 'user_external_id', 'duration_us', 'trace_id', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
