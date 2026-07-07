<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinitorRequest extends Model
{
    protected $table = 'requests';

    public $timestamps = false;

    protected $fillable = [
        'project_id', 'method', 'url', 'route_name', 'route_path', 'route_action',
        'ip', 'status_code', 'duration_us', 'request_size', 'response_size',
        'deploy', 'trace_id', 'user_external_id', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
