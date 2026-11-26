<?php

declare(strict_types=1);

namespace App\Data\Finance;

final readonly class TransferQueueCounts
{
    public function __construct(public int $underReview, public int $approvedToday, public int $rejectedToday) {}

    /**
     * @return array{under_review: int, approved_today: int, rejected_today: int}
     */
    public function toArray(): array
    {
        return [
            'under_review' => $this->underReview,
            'approved_today' => $this->approvedToday,
            'rejected_today' => $this->rejectedToday,
        ];
    }
}
