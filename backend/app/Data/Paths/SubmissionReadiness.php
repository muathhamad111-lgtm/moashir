<?php

declare(strict_types=1);

namespace App\Data\Paths;

use App\Enums\Periods\SubmissionBlock;
use Carbon\CarbonImmutable;

final readonly class SubmissionReadiness
{
    public function __construct(public ?SubmissionBlock $block = null, public ?CarbonImmutable $nextSubmissionFrom = null) {}

    public function canSubmitNow(): bool
    {
        return $this->block === null;
    }

    /**
     * @return array{can_submit_now: bool, reason: string|null, next_submission_from: string|null}
     */
    public function toArray(): array
    {
        return [
            'can_submit_now' => $this->canSubmitNow(),
            'reason' => $this->block?->value,
            'next_submission_from' => $this->nextSubmissionFrom?->toDateString(),
        ];
    }
}
