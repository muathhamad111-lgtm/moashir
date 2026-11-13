<?php

declare(strict_types=1);

use App\Enums\Certificates\CertificateLevel;
use App\Enums\Certificates\EntityLevel;
use App\Enums\Certificates\EntityLevelBucket;
use App\Enums\Certificates\EntityLevelFilter;

it('maps a certificate without a metal level to participation', function (?CertificateLevel $certificate, EntityLevel $expected): void {
    expect(EntityLevel::ofCertificate($certificate))->toBe($expected);
})->with([
    'no metal level' => [null, EntityLevel::Participation],
    'bronze' => [CertificateLevel::Bronze, EntityLevel::Bronze],
    'silver' => [CertificateLevel::Silver, EntityLevel::Silver],
    'gold' => [CertificateLevel::Gold, EntityLevel::Gold],
    'diamond' => [CertificateLevel::Diamond, EntityLevel::Diamond],
]);

it('resolves the level of a filter value, none and expired meaning no valid level', function (EntityLevelFilter $filter, ?EntityLevel $expected): void {
    expect($filter->level())->toBe($expected);
})->with([
    'none' => [EntityLevelFilter::None, null],
    'expired' => [EntityLevelFilter::Expired, null],
    'participation' => [EntityLevelFilter::Participation, EntityLevel::Participation],
    'gold' => [EntityLevelFilter::Gold, EntityLevel::Gold],
]);

it('puts an entity in the in-progress bucket only while it has no level', function (?EntityLevel $level, bool $open, EntityLevelBucket $expected): void {
    expect(EntityLevelBucket::of($level, $open))->toBe($expected);
})->with([
    'nothing' => [null, false, EntityLevelBucket::None],
    'open request, no level' => [null, true, EntityLevelBucket::InProgress],
    'open request with a level' => [EntityLevel::Gold, true, EntityLevelBucket::Gold],
    'participation' => [EntityLevel::Participation, false, EntityLevelBucket::Participation],
]);
