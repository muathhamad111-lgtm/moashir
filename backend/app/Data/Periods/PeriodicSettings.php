<?php

declare(strict_types=1);

namespace App\Data\Periods;

use App\Enums\Periods\CertificateValidityRule;
use App\Enums\Periods\PeriodCadence;
use App\Enums\Periods\PeriodicAudience;
use Carbon\CarbonImmutable;

final readonly class PeriodicSettings
{
    /**
     * @param  list<int>  $reminderDays
     */
    public function __construct(
        public PeriodCadence $cadence,
        public int $noticeDays,
        public array $reminderDays,
        public PeriodicAudience $audience,
        public CertificateValidityRule $certificateValidity,
        public int $paidDraftReminderDays,
        public ?CarbonImmutable $rolloutFrom,
    ) {}

    public function largestReminderDays(): int
    {
        return $this->reminderDays === [] ? 0 : max($this->reminderDays);
    }

    public function isLiveOn(CarbonImmutable $day): bool
    {
        return $this->rolloutFrom instanceof CarbonImmutable && $day->toDateString() >= $this->rolloutFrom->toDateString();
    }
}
