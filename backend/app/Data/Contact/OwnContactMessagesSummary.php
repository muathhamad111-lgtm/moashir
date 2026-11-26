<?php

declare(strict_types=1);

namespace App\Data\Contact;

final readonly class OwnContactMessagesSummary
{
    /**
     * @param  array<string, int>  $statusCounts
     */
    public function __construct(public array $statusCounts, public int $unreadReplies) {}

    /**
     * @return array{status_counts: array<string, int>, unread_replies: int}
     */
    public function toMeta(): array
    {
        return ['status_counts' => $this->statusCounts, 'unread_replies' => $this->unreadReplies];
    }
}
