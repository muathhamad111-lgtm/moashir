<?php

declare(strict_types=1);

use App\Enums\Certificates\CertificateLevel;
use App\Support\ValueObjects\LevelThresholds;
use App\Support\ValueObjects\Percent;

it('maps a result to the excellence level of the default thresholds', function (string $percent, ?CertificateLevel $level): void {
    expect(LevelThresholds::defaults()->levelFor(Percent::of($percent)))->toBe($level);
})->with([
    'below bronze' => ['59.99', null],
    'zero' => ['0', null],
    'bronze floor' => ['60.00', CertificateLevel::Bronze],
    'bronze ceiling' => ['69.99', CertificateLevel::Bronze],
    'silver floor' => ['70.00', CertificateLevel::Silver],
    'gold' => ['87.50', CertificateLevel::Gold],
    'diamond floor' => ['90.00', CertificateLevel::Diamond],
    'full marks' => ['100', CertificateLevel::Diamond],
]);

it('decides the level on the two-decimal percentage shown on the certificate', function (): void {
    $thresholds = LevelThresholds::defaults();

    expect($thresholds->levelFor(Percent::of('69.9950')))->toBe(CertificateLevel::Silver)
        ->and($thresholds->levelFor(Percent::of('69.9949')))->toBe(CertificateLevel::Bronze)
        ->and($thresholds->levelFor(Percent::of('59.9950')))->toBe(CertificateLevel::Bronze);
});

it('keeps edited thresholds as two-decimal strings', function (): void {
    $thresholds = LevelThresholds::fromArray(['bronze' => '55', 'silver' => '65.5', 'gold' => '75.25', 'diamond' => '88.00']);

    expect($thresholds->toArray())->toBe(['bronze' => '55.00', 'silver' => '65.50', 'gold' => '75.25', 'diamond' => '88.00'])
        ->and($thresholds->levelFor(Percent::of('54.99')))->toBeNull()
        ->and($thresholds->levelFor(Percent::of('80.00')))->toBe(CertificateLevel::Gold);
});

it('refuses missing, zero or non-ascending minimums', function (array $values): void {
    expect(fn (): LevelThresholds => LevelThresholds::fromArray($values))->toThrow(InvalidArgumentException::class);
})->with([
    'missing diamond' => [['bronze' => '60.00', 'silver' => '70.00', 'gold' => '80.00']],
    'zero bronze' => [['bronze' => '0', 'silver' => '70.00', 'gold' => '80.00', 'diamond' => '90.00']],
    'equal levels' => [['bronze' => '60.00', 'silver' => '60.00', 'gold' => '80.00', 'diamond' => '90.00']],
    'descending' => [['bronze' => '70.00', 'silver' => '60.00', 'gold' => '80.00', 'diamond' => '90.00']],
    'not a number' => [['bronze' => 'sixty', 'silver' => '70.00', 'gold' => '80.00', 'diamond' => '90.00']],
]);
