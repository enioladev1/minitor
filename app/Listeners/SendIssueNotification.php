<?php

namespace App\Listeners;

use App\Events\IssueCreated;
use App\Mail\IssueAlertMail;
use App\Models\NotificationSetting;
use App\Services\Mail\DynamicMailerFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Auto-discovered by Laravel's listener discovery (this app has no
 * EventServiceProvider $listen array) purely from this handle() method's
 * IssueCreated type-hint - no manual registration needed.
 */
class SendIssueNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(IssueCreated $event): void
    {
        $settings = NotificationSetting::current();

        if (! $settings->enabled || ! $settings->isConfigured()) {
            return;
        }

        if (! $event->issue->project->notify_on_new_issue) {
            return;
        }

        if ($event->isReopen && ! $settings->notify_on_reopen) {
            return;
        }

        $recipient = $settings->notify_email ?: $event->issue->project->user->email;

        $mailerName = app(DynamicMailerFactory::class)->apply($settings);

        Mail::mailer($mailerName)
            ->to($recipient)
            ->send(new IssueAlertMail($event->issue, $event->isReopen));
    }

    public function failed(IssueCreated $event, Throwable $exception): void
    {
        Log::warning('Failed to send issue notification email', [
            'issue_id' => $event->issue->id,
            'project_id' => $event->issue->project_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
