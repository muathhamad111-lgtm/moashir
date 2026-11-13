<?php

declare(strict_types=1);

use App\Enums\Certificates\CertificateLevel;
use App\Enums\Certificates\CertificateStatus;
use App\Models\Certificates\Certificate;
use App\Models\Entities\Entity;
use App\Support\Certificates\EntityLevelPresenter;
use Illuminate\Support\Facades\Date;

const LAST_LEVEL_UNIT_TODAY = '2027-05-31 10:00';

function entityWithLastLevel(?CertificateLevel $level, ?string $validUntil): Entity
{
    $certificate = new Certificate;
    $certificate->forceFill(['level' => $level, 'status' => CertificateStatus::Issued, 'valid_until' => $validUntil]);

    return new Entity()->setRelation('lastLevelCertificate', $certificate);
}

beforeEach(function (): void {
    Date::setTestNow(Date::parse(LAST_LEVEL_UNIT_TODAY, RIYADH));
});

it('presents the last level with its validity whatever its expiry date', function (?CertificateLevel $level, string $validUntil, array $expected): void {
    expect(EntityLevelPresenter::lastLevelOf(entityWithLastLevel($level, $validUntil)))->toBe($expected);
})->with([
    'valid gold' => [CertificateLevel::Gold, '2027-07-01', ['level' => 'gold', 'valid_until' => '2027-07-01', 'validity' => 'valid', 'days_until_expiry' => 31]],
    'expiring silver' => [CertificateLevel::Silver, '2027-06-10', ['level' => 'silver', 'valid_until' => '2027-06-10', 'validity' => 'expiring_soon', 'days_until_expiry' => 10]],
    'expired bronze' => [CertificateLevel::Bronze, '2027-05-30', ['level' => 'bronze', 'valid_until' => '2027-05-30', 'validity' => 'expired', 'days_until_expiry' => null]],
    'expired participation' => [null, '2027-01-31', ['level' => 'participation', 'valid_until' => '2027-01-31', 'validity' => 'expired', 'days_until_expiry' => null]],
]);

it('presents no last level for an entity without an issued accredited certificate', function (): void {
    expect(EntityLevelPresenter::lastLevelOf(new Entity()->setRelation('lastLevelCertificate', null)))->toBeNull();
});

it('keeps the counted level empty while the last level is expired', function (): void {
    $entity = entityWithLastLevel(CertificateLevel::Silver, '2027-05-30')->setRelation('levelCertificate', null);

    expect(EntityLevelPresenter::of($entity))->toBe(['level' => null, 'level_certificate_number' => null, 'level_valid_until' => null])
        ->and(EntityLevelPresenter::lastLevelOf($entity)['level'] ?? null)->toBe('silver');
});
