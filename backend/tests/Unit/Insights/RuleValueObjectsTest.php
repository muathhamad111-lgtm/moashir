<?php

declare(strict_types=1);

use App\Enums\Insights\GapClass;
use App\Enums\Insights\MaturityBand;
use App\Support\ValueObjects\GapThresholds;
use App\Support\ValueObjects\MaturityBounds;
use App\Support\ValueObjects\Percent;
use App\Support\ValueObjects\PointsDelta;
use App\Support\ValueObjects\VatRegistrationNumber;

it('classifies gaps on the displayed percent at the exact boundaries', function (string $percent, GapClass $class): void {
    expect(GapThresholds::defaults()->classify(Percent::of($percent)))->toBe($class);
})->with([
    ['49.99', GapClass::CriticalGap],
    ['49.994', GapClass::CriticalGap],
    ['49.995', GapClass::Opportunity],
    ['50.00', GapClass::Opportunity],
    ['79.99', GapClass::Opportunity],
    ['80.00', GapClass::Strength],
    ['0', GapClass::CriticalGap],
    ['100', GapClass::Strength],
]);

it('bands the displayed percent at the exact boundaries', function (string $percent, MaturityBand $band): void {
    expect(MaturityBounds::defaults()->bandFor(Percent::of($percent)))->toBe($band);
})->with([
    ['49.99', MaturityBand::Emerging],
    ['50.00', MaturityBand::Developing],
    ['69.99', MaturityBand::Developing],
    ['70.00', MaturityBand::Mature],
    ['79.99', MaturityBand::Mature],
    ['79.995', MaturityBand::Leading],
    ['80.00', MaturityBand::Leading],
]);

it('refuses rule sets that are not ascending, out of range or too precise', function (Closure $build): void {
    expect($build)->toThrow(InvalidArgumentException::class);
})->with([
    'gaps equal' => [static fn (): GapThresholds => GapThresholds::of('60.00', '60.00')],
    'gaps zero' => [static fn (): GapThresholds => GapThresholds::of('0', '60.00')],
    'gaps above 100' => [static fn (): GapThresholds => GapThresholds::of('50.00', '100.01')],
    'gaps three decimals' => [static fn (): GapThresholds => GapThresholds::of('50.001', '80.00')],
    'bands descending' => [static fn (): MaturityBounds => MaturityBounds::of('50.00', '45.00', '80.00')],
    'bands above 100' => [static fn (): MaturityBounds => MaturityBounds::of('50.00', '70.00', '101')],
    'bands version zero' => [static fn (): MaturityBounds => MaturityBounds::of('50.00', '70.00', '80.00', 0)],
]);

it('raises the version only when the bounds change', function (): void {
    $current = GapThresholds::defaults();

    expect($current->revisedTo(GapThresholds::of('50', '80.0')))->toBe($current)
        ->and($current->revisedTo(GapThresholds::of('45.00', '80.00'))->toArray())->toBe(['critical_below' => '45.00', 'strength_from' => '80.00', 'version' => 2])
        ->and(MaturityBounds::defaults()->revisedTo(MaturityBounds::of('40', '60', '90'))->version)->toBe(2);
});

it('formats signed percentage-point deltas on displayed values', function (string $current, string $previous, string $delta): void {
    expect(PointsDelta::between(Percent::of($current), Percent::of($previous))->toDisplay())->toBe($delta);
})->with([
    ['75.50', '70.00', '5.50'],
    ['60.00', '72.25', '-12.25'],
    ['70.004', '70.00', '0.00'],
    ['0.5', '1', '-0.50'],
]);

it('accepts only fifteen Western digits starting and ending with 3 as VAT registration number', function (mixed $value, bool $valid): void {
    expect(VatRegistrationNumber::tryFrom($value) instanceof VatRegistrationNumber)->toBe($valid);
})->with([
    ['300000000000003', true],
    ['312345678901233', true],
    ['30000000000003', false],
    ['3000000000000003', false],
    ['200000000000003', false],
    ['300000000000002', false],
    ['3000000000000A3', false],
    ['300000 00000003', false],
    ['٣٠٠٠٠٠٠٠٠٠٠٠٠٠٣', false],
    [300000000000003, false],
    [null, false],
]);
