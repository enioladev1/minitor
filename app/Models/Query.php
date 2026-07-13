<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Query extends Model
{
    protected $table = 'queries';

    public $timestamps = false;

    protected $fillable = [
        'project_id', 'sql', 'query_group', 'file', 'line', 'duration_us',
        'connection', 'connection_type', 'execution_source', 'execution_stage',
        'trace_id', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    // See MinitorRequest::$dateFormat - needed for accurate Timeline offsets.
    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
