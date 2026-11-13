<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class SubmissionProgress
{
    /**
     * @param  list<RemainingSub>  $remaining
     */
    public function __construct(public int $subsTotal, public int $subsComplete, public array $remaining) {}

    public function remainingCount(): int
    {
        return count($this->remaining);
    }

    /**
     * @return array{subs_total: int, subs_complete: int, remaining: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'subs_total' => $this->subsTotal,
            'subs_complete' => $this->subsComplete,
            'remaining' => array_map(static fn (RemainingSub $sub): array => $sub->toArray(), $this->remaining),
        ];
    }
}
