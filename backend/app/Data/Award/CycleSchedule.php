<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Models\Award\AwardCycle;
use Carbon\CarbonImmutable;

final readonly class CycleSchedule
{
    public function __construct(
        public CarbonImmutable $submissionStart,
        public CarbonImmutable $submissionEnd,
        public CarbonImmutable $committeeStart,
        public CarbonImmutable $committeeEnd,
        public ?CarbonImmutable $announcementAt,
    ) {}

    public static function of(AwardCycle $cycle): self
    {
        return new self(
            CarbonImmutable::instance($cycle->submission_start),
            CarbonImmutable::instance($cycle->submission_end),
            CarbonImmutable::instance($cycle->committee_start),
            CarbonImmutable::instance($cycle->committee_end),
            $cycle->announcement_at === null ? null : CarbonImmutable::instance($cycle->announcement_at),
        );
    }

    /**
     * @param  array<string, CarbonImmutable|null>  $dates
     */
    public static function from(array $dates): ?self
    {
        $required = [$dates['submission_start'] ?? null, $dates['submission_end'] ?? null, $dates['committee_start'] ?? null, $dates['committee_end'] ?? null];

        if (in_array(null, $required, true)) {
            return null;
        }

        [$submissionStart, $submissionEnd, $committeeStart, $committeeEnd] = $required;

        return new self($submissionStart, $submissionEnd, $committeeStart, $committeeEnd, $dates['announcement_at'] ?? null);
    }

    /**
     * @param  array<string, CarbonImmutable|null>  $changes
     */
    public function with(array $changes): self
    {
        return new self(
            $changes['submission_start'] ?? $this->submissionStart,
            $changes['submission_end'] ?? $this->submissionEnd,
            $changes['committee_start'] ?? $this->committeeStart,
            $changes['committee_end'] ?? $this->committeeEnd,
            array_key_exists('announcement_at', $changes) ? $changes['announcement_at'] : $this->announcementAt,
        );
    }

    public function isOrdered(): bool
    {
        return $this->submissionStart->lessThan($this->submissionEnd)
            && $this->submissionEnd->lessThanOrEqualTo($this->committeeStart)
            && $this->committeeStart->lessThan($this->committeeEnd)
            && (! $this->announcementAt instanceof CarbonImmutable || $this->announcementAt->greaterThanOrEqualTo($this->committeeEnd));
    }
}
