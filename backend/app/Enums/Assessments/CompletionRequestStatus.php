<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum CompletionRequestStatus: string
{
    case Open = 'open';
    case Resubmitted = 'resubmitted';
    case Cancelled = 'cancelled';
    case ExpiredContinued = 'expired_continued';
    case ExpiredDeclined = 'expired_declined';

    /**
     * The closed statuses after which the review goes on in a new round: a cancelled request opens none.
     *
     * @return list<self>
     */
    public static function roundOpeners(): array
    {
        return [self::Resubmitted, self::ExpiredContinued, self::ExpiredDeclined];
    }

    public function asksForRescore(): bool
    {
        return $this === self::Resubmitted;
    }
}
