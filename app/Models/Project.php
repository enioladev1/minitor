<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->token ??= self::generateToken();
            $project->local_secret ??= self::generateLocalSecret();
        });
    }

    public static function generateToken(): string
    {
        return 'minitor_'.Str::random(40);
    }

    /**
     * The value that goes into a monitored app's NIGHTWATCH_TOKEN - a local
     * handshake secret between the real laravel/nightwatch package and
     * minitor/agent on that same server. It's unrelated to $token (which
     * authenticates that server to us over the network); it's generated here
     * purely so nobody has to invent or type one by hand.
     */
    public static function generateLocalSecret(): string
    {
        return Str::random(40);
    }

    /**
     * Rotates both credentials together: the project token (network auth to
     * this dashboard) and the local secret (NIGHTWATCH_TOKEN on the
     * monitored server). Both must be updated in that app's .env afterward.
     */
    public function regenerateToken(): string
    {
        $this->token = self::generateToken();
        $this->local_secret = self::generateLocalSecret();
        $this->save();

        return $this->token;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(MinitorRequest::class);
    }

    public function queries(): HasMany
    {
        return $this->hasMany(Query::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(MinitorException::class);
    }

    public function queuedJobs(): HasMany
    {
        return $this->hasMany(QueuedJob::class);
    }

    public function jobAttempts(): HasMany
    {
        return $this->hasMany(JobAttempt::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(AppCommand::class);
    }

    public function scheduledTasks(): HasMany
    {
        return $this->hasMany(ScheduledTask::class);
    }

    public function cacheEvents(): HasMany
    {
        return $this->hasMany(CacheEvent::class);
    }

    public function outgoingRequests(): HasMany
    {
        return $this->hasMany(OutgoingRequest::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LogEntry::class);
    }

    public function monitoredUsers(): HasMany
    {
        return $this->hasMany(MonitoredUser::class);
    }
}
