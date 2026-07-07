<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinitorException extends Model
{
    protected $table = 'exceptions';

    public $timestamps = false;

    protected $fillable = [
        'project_id', 'issue_id', 'message', 'code', 'handled', 'trace',
        'trace_id', 'php_version', 'laravel_version', 'user_external_id',
        'server', 'execution_source', 'execution_preview', 'occurred_at',
    ];

    protected $casts = [
        'handled' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
