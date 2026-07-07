<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingRequest extends Model
{
    protected $table = 'outgoing_requests';

    public $timestamps = false;

    protected $fillable = [
        'project_id', 'host', 'method', 'url', 'duration_us',
        'request_size', 'response_size', 'status_code',
        'execution_source', 'execution_preview', 'trace_id', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
