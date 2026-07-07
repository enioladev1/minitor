<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppCommand extends Model
{
    protected $table = 'commands';

    public $timestamps = false;

    protected $fillable = [
        'project_id', 'class', 'name', 'command', 'exit_code',
        'duration_us', 'trace_id', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
