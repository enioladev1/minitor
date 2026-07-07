<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueEvent extends Model
{
    protected $fillable = ['issue_id', 'user_id', 'type', 'body', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
