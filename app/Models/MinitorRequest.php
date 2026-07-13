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
        'bootstrap_us', 'before_middleware_us', 'action_us', 'render_us',
        'after_middleware_us', 'sending_us', 'terminating_us',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    // Eloquent's default 'Y-m-d H:i:s' write format truncates to whole
    // seconds regardless of the column's own precision - the Timeline
    // waterfall needs the microsecond offsets Nightwatch actually sends.
    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
