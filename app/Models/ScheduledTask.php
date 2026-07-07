<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTask extends Model
{
    protected $table = 'scheduled_tasks';

    public $timestamps = false;

    protected $fillable = [
        'project_id', 'name', 'cron', 'timezone', 'repeat_seconds',
        'without_overlapping', 'on_one_server', 'run_in_background',
        'even_in_maintenance_mode', 'status', 'duration_us', 'trace_id', 'occurred_at',
    ];

    protected $casts = [
        'without_overlapping' => 'boolean',
        'on_one_server' => 'boolean',
        'run_in_background' => 'boolean',
        'even_in_maintenance_mode' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
