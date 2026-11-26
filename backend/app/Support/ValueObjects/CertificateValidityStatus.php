<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Enums\Certificates\CertificateValidityState;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class CertificateValidityStatus
{
    private const string CALENDAR_TIMEZONE = 'UTC';

    public function __construct(public CertificateValidityState $state, public ?int $daysLeft)
    {
        if ($daysLeft !== null && $daysLeft < 0) {
            throw new InvalidArgumentException('The days left on a certificate cannot be negative.');
        }
    }

    public static function on(?CarbonImmutable $validUntil, CarbonImmutable $today, int $expiringSoonDays): self
    {
        if (! $validUntil instanceof CarbonImmutable) {
            return new self(CertificateValidityState::Valid, null);
        }

        $daysLeft = self::calendarDaysBetween($today, $validUntil);

        return new self(CertificateValidityState::forDaysLeft($daysLeft, $expiringSoonDays), $daysLeft >= 0 ? $daysLeft : null);
    }

    private static function calendarDaysBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $start = CarbonImmutable::parse($from->toDateString(), self::CALENDAR_TIMEZONE);
        $end = CarbonImmutable::parse($to->toDateString(), self::CALENDAR_TIMEZONE);

        return (int) $start->diffInDays($end, false);
    }
}
