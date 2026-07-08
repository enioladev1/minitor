<?php

namespace App\Events;

use App\Models\Issue;

/**
 * Fired when a fingerprinted exception becomes a brand-new Issue, or when a
 * previously-resolved Issue recurs. RecordIngestor stays persistence-only -
 * this is the seam for side effects like SendIssueNotification without
 * touching the ingestion hot path again for future notification channels.
 */
class IssueCreated
{
    public function __construct(
        public readonly Issue $issue,
        public readonly bool $isReopen = false,
    ) {}
}
