<?php

declare(strict_types=1);

use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\ComparisonBasis;
use App\Enums\Dashboards\OverviewPeriod;
use App\Services\Dashboards\Overview\ReportingPeriods;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * @return array{from: string, to: string}
 */
function windowDays(ReportingPeriod $period): array
{
    return ['from' => $period->start->format('Y-m-d'), 'to' => $period->lastDay()->format('Y-m-d')];
}

function dayIn(string $day): CarbonImmutable
{
    return CarbonImmutable::parse($day, config()->string('app.display_timezone'));
}

it('compares a running month, quarter or year with the same span of the previous one', function (string $today, OverviewPeriod $preset, array $period, array $previous): void {
    Date::setTestNow(dayIn($today)->setTime(9, 0));

    $chosen = app(ReportingPeriods::class)->preset($preset);

    expect(windowDays($chosen))->toBe($period)
        ->and(windowDays($chosen->previous()))->toBe($previous)
        ->and($chosen->comparisonBasis())->toBe(ComparisonBasis::ElapsedSpan);
})->with([
    'month' => ['2026-11-25', OverviewPeriod::Month, ['from' => '2026-11-01', 'to' => '2026-11-25'], ['from' => '2026-10-01', 'to' => '2026-10-25']],
    'quarter' => ['2026-11-25', OverviewPeriod::Quarter, ['from' => '2026-10-01', 'to' => '2026-11-25'], ['from' => '2026-07-01', 'to' => '2026-08-25']],
    'year' => ['2026-11-25', OverviewPeriod::Year, ['from' => '2026-01-01', 'to' => '2026-11-25'], ['from' => '2025-01-01', 'to' => '2025-11-25']],
    'month end kept inside February' => ['2027-03-30', OverviewPeriod::Month, ['from' => '2027-03-01', 'to' => '2027-03-30'], ['from' => '2027-02-01', 'to' => '2027-02-28']],
    'leap day in a running year' => ['2028-02-29', OverviewPeriod::Year, ['from' => '2028-01-01', 'to' => '2028-02-29'], ['from' => '2027-01-01', 'to' => '2027-02-28']],
    'first day of a quarter' => ['2026-10-01', OverviewPeriod::Quarter, ['from' => '2026-10-01', 'to' => '2026-10-01'], ['from' => '2026-07-01', 'to' => '2026-07-01']],
]);

it('compares a finished month, quarter or year with the previous one in full', function (string $within, OverviewPeriod $preset, array $period, array $previous): void {
    Date::setTestNow(dayIn('2028-11-25')->setTime(9, 0));

    $chosen = app(ReportingPeriods::class)->preset($preset, dayIn($within));

    expect(windowDays($chosen))->toBe($period)
        ->and(windowDays($chosen->previous()))->toBe($previous)
        ->and($chosen->comparisonBasis())->toBe(ComparisonBasis::PreviousFull);
})->with([
    'month' => ['2026-09-01', OverviewPeriod::Month, ['from' => '2026-09-01', 'to' => '2026-09-30'], ['from' => '2026-08-01', 'to' => '2026-08-31']],
    'March after a short February' => ['2027-03-01', OverviewPeriod::Month, ['from' => '2027-03-01', 'to' => '2027-03-31'], ['from' => '2027-02-01', 'to' => '2027-02-28']],
    'leap February' => ['2028-02-01', OverviewPeriod::Month, ['from' => '2028-02-01', 'to' => '2028-02-29'], ['from' => '2028-01-01', 'to' => '2028-01-31']],
    'first quarter' => ['2026-01-01', OverviewPeriod::Quarter, ['from' => '2026-01-01', 'to' => '2026-03-31'], ['from' => '2025-10-01', 'to' => '2025-12-31']],
    'year' => ['2027-01-01', OverviewPeriod::Year, ['from' => '2027-01-01', 'to' => '2027-12-31'], ['from' => '2026-01-01', 'to' => '2026-12-31']],
]);

it('treats a month on its last day as complete and compares it with the previous one in full', function (): void {
    Date::setTestNow(dayIn('2026-11-30')->setTime(9, 0));

    $chosen = app(ReportingPeriods::class)->preset(OverviewPeriod::Month);

    expect(windowDays($chosen))->toBe(['from' => '2026-11-01', 'to' => '2026-11-30'])
        ->and(windowDays($chosen->previous()))->toBe(['from' => '2026-10-01', 'to' => '2026-10-31'])
        ->and($chosen->comparisonBasis())->toBe(ComparisonBasis::PreviousFull);
});

it('compares a custom range with the same number of days just before it', function (): void {
    Date::setTestNow(dayIn('2026-11-25')->setTime(9, 0));

    $chosen = app(ReportingPeriods::class)->custom('2026-09-01', '2026-09-30');

    expect(windowDays($chosen->previous()))->toBe(['from' => '2026-08-02', 'to' => '2026-08-31'])
        ->and($chosen->comparisonBasis())->toBe(ComparisonBasis::PrecedingDays);
});

it('starts the boards on the configured first month', function (): void {
    config(['moashir.dashboard.first_month' => '2026-01']);

    expect(app(ReportingPeriods::class)->firstDay()->format('Y-m-d'))->toBe('2026-01-01');
});
