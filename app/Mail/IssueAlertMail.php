<?php

namespace App\Mail;

use App\Models\Issue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent by SendIssueNotification when a project's first occurrence of a new
 * exception fingerprint lands, or when a resolved Issue recurs. Deliberately
 * only ever touches the Issue model, which has no trace field - the
 * underlying MinitorException.trace is never loaded here, so a stack trace
 * cannot leak into this email even by accident.
 */
class IssueAlertMail extends Mailable implements ShouldQueue
{
    use SerializesModels;

    public function __construct(
        public readonly Issue $issue,
        public readonly bool $isReopen = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->isReopen ? '[Reopened] ' : '[New Issue] ')
                .$this->issue->project->name.': '.$this->issue->class,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.issue-alert',
            with: [
                'projectName' => $this->issue->project->name,
                'issueClass' => $this->issue->class,
                'message' => $this->issue->last_message,
                'occurredAt' => $this->issue->last_seen_at,
                'occurrencesCount' => $this->issue->occurrences_count,
                'isReopen' => $this->isReopen,
                'issueUrl' => route('projects.issues.show', [
                    'project' => $this->issue->project_id,
                    'issue' => $this->issue->id,
                ]),
            ],
        );
    }
}
