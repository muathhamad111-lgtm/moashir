<?php

declare(strict_types=1);

use App\Enums\Certificates\CertificateStatus;
use App\Enums\Certificates\CertificateValidityState;
use App\Models\Certificates\Certificate;
use App\Support\ValueObjects\CertificateValidityStatus;
use Carbon\CarbonImmutable;

const VALIDITY_TODAY = '2027-05-31';
const EXPIRING_SOON_DAYS = 30;

function validityDay(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date, RIYADH)->startOfDay();
}

it('derives the validity state from the days left before the expiry date', function (string $validUntil, CertificateValidityState $state, ?int $daysLeft): void {
    $status = CertificateValidityStatus::on(CarbonImmutable::parse($validUntil), validityDay(VALIDITY_TODAY), EXPIRING_SOON_DAYS);

    expect($status->state)->toBe($state)
        ->and($status->daysLeft)->toBe($daysLeft);
})->with([
    '31 days left' => ['2027-07-01', CertificateValidityState::Valid, 31],
    '30 days left' => ['2027-06-30', CertificateValidityState::ExpiringSoon, 30],
    '1 day left' => ['2027-06-01', CertificateValidityState::ExpiringSoon, 1],
    'last valid day' => ['2027-05-31', CertificateValidityState::ExpiringSoon, 0],
    'one day past the date' => ['2027-05-30', CertificateValidityState::Expired, null],
    'long past the date' => ['2026-12-31', CertificateValidityState::Expired, null],
]);

it('compares calendar days even when the expiry date and today sit in different time zones', function (): void {
    $validUntil = CarbonImmutable::parse('2027-05-31', 'UTC');

    expect(CertificateValidityStatus::on($validUntil, validityDay('2027-05-31'), EXPIRING_SOON_DAYS)->state)->toBe(CertificateValidityState::ExpiringSoon)
        ->and(CertificateValidityStatus::on($validUntil, validityDay('2027-06-01'), EXPIRING_SOON_DAYS)->state)->toBe(CertificateValidityState::Expired);
});

it('reads a certificate without an expiry date as valid with no countdown', function (): void {
    $status = CertificateValidityStatus::on(null, validityDay(VALIDITY_TODAY), EXPIRING_SOON_DAYS);

    expect($status->state)->toBe(CertificateValidityState::Valid)
        ->and($status->daysLeft)->toBeNull();
});

it('follows the configured threshold instead of a fixed number of days', function (): void {
    $validUntil = CarbonImmutable::parse('2027-07-15');

    expect(CertificateValidityStatus::on($validUntil, validityDay(VALIDITY_TODAY), 60)->state)->toBe(CertificateValidityState::ExpiringSoon)
        ->and(CertificateValidityStatus::on($validUntil, validityDay(VALIDITY_TODAY), 30)->state)->toBe(CertificateValidityState::Valid);
});

it('refuses a negative countdown', function (): void {
    expect(fn () => new CertificateValidityStatus(CertificateValidityState::Valid, -1))->toThrow(InvalidArgumentException::class);
});

it('gives a validity only to an issued certificate', function (CertificateStatus $status, ?CertificateValidityState $expected): void {
    $certificate = new Certificate(['valid_until' => '2027-06-10']);
    $certificate->status = $status;

    expect($certificate->validityOn(validityDay(VALIDITY_TODAY), EXPIRING_SOON_DAYS)?->state)->toBe($expected);
})->with([
    'issued' => [CertificateStatus::Issued, CertificateValidityState::ExpiringSoon],
    'suspended' => [CertificateStatus::Suspended, null],
    'revoked' => [CertificateStatus::Revoked, null],
    'superseded' => [CertificateStatus::Superseded, null],
]);
