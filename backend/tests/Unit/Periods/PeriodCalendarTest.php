<?php

declare(strict_types=1);

use App\Data\Periods\PeriodicSettings;
use App\Enums\Periods\CertificateValidityRule;
use App\Enums\Periods\PeriodCadence;
use App\Enums\Periods\PeriodicAudience;
use App\Support\Periods\LocalDay;
use App\Support\Periods\PeriodCalendar;
use App\Support\Periods\PeriodPlanner;
use App\Support\ValueObjects\PeriodWindow;
use Carbon\CarbonImmutable;

function riyadhDay(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date, 'Asia/Riyadh');
}

function plannerSettings(PeriodCadence $cadence, ?string $rollout = '2027-01-01'): PeriodicSettings
{
    return new PeriodicSettings(
        $cadence,
        $cadence->defaultNoticeDays(),
        $cadence->defaultReminderDays(),
        PeriodicAudience::ActiveVerifiedEntities,
        CertificateValidityRule::NextPeriodEnd,
        7,
        $rollout === null ? null : riyadhDay($rollout),
    );
}

/**
 * @param  list<PeriodWindow>  $windows
 * @return list<string>
 */
function windowSpans(array $windows): array
{
    return array_map(static fn (PeriodWindow $window): string => sprintf('%s %s..%s', $window->key(), $window->startsOn->toDateString(), $window->endsOn->toDateString()), $windows);
}

it('finds the calendar period of a day for every cadence', function (PeriodCadence $cadence, string $day, string $key, string $start, string $end, string $ar, string $en): void {
    $window = PeriodWindow::containing($cadence, riyadhDay($day));

    expect($window->key())->toBe($key)
        ->and($window->startsOn->toDateString())->toBe($start)
        ->and($window->endsOn->toDateString())->toBe($end)
        ->and($window->isShortened())->toBeFalse()
        ->and($window->label()->toArray())->toBe(['ar' => $ar, 'en' => $en]);
})->with([
    'quarterly Q1' => [PeriodCadence::Quarterly, '2027-02-14', '2027-Q1', '2027-01-01', '2027-03-31', 'الربع الأول 2027', 'Q1 2027'],
    'quarterly Q4' => [PeriodCadence::Quarterly, '2027-12-31', '2027-Q4', '2027-10-01', '2027-12-31', 'الربع الرابع 2027', 'Q4 2027'],
    'semi-annual H2' => [PeriodCadence::SemiAnnual, '2027-07-01', '2027-H2', '2027-07-01', '2027-12-31', 'النصف الثاني 2027', 'H2 2027'],
    'semi-annual leap February' => [PeriodCadence::SemiAnnual, '2028-02-29', '2028-H1', '2028-01-01', '2028-06-30', 'النصف الأول 2028', 'H1 2028'],
    'annual' => [PeriodCadence::Annual, '2027-06-15', '2027', '2027-01-01', '2027-12-31', 'عام 2027', '2027'],
]);

it('uses Asia/Riyadh midnight as the boundary of a period', function (): void {
    $window = PeriodWindow::containing(PeriodCadence::Quarterly, riyadhDay('2027-03-31'));

    expect($window->startsAt()->toIso8601String())->toBe('2026-12-31T21:00:00+00:00')
        ->and($window->endsAt()->toIso8601String())->toBe('2027-03-31T21:00:00+00:00')
        ->and(LocalDay::of(CarbonImmutable::parse('2027-03-31T20:59:59Z'))->toDateString())->toBe('2027-03-31')
        ->and(LocalDay::of(CarbonImmutable::parse('2027-03-31T21:00:00Z'))->toDateString())->toBe('2027-04-01');
});

it('lists consecutive periods without gaps for every cadence', function (PeriodCadence $cadence, array $expected): void {
    expect(windowSpans(PeriodCalendar::sequence($cadence, riyadhDay('2027-01-01'), 4)))->toBe($expected);
})->with([
    'quarterly' => [PeriodCadence::Quarterly, ['2027-Q1 2027-01-01..2027-03-31', '2027-Q2 2027-04-01..2027-06-30', '2027-Q3 2027-07-01..2027-09-30', '2027-Q4 2027-10-01..2027-12-31']],
    'semi-annual' => [PeriodCadence::SemiAnnual, ['2027-H1 2027-01-01..2027-06-30', '2027-H2 2027-07-01..2027-12-31', '2028-H1 2028-01-01..2028-06-30', '2028-H2 2028-07-01..2028-12-31']],
    'annual' => [PeriodCadence::Annual, ['2027 2027-01-01..2027-12-31', '2028 2028-01-01..2028-12-31', '2029 2029-01-01..2029-12-31', '2030 2030-01-01..2030-12-31']],
]);

it('shortens the first period of a new cadence to start the day after the current period ends', function (): void {
    $current = PeriodWindow::containing(PeriodCadence::Quarterly, riyadhDay('2027-02-10'));
    $plan = PeriodPlanner::plan(plannerSettings(PeriodCadence::SemiAnnual), riyadhDay('2027-02-10'), $current->endsOn);

    expect($plan->started)->toBe([])
        ->and(windowSpans($plan->upcoming))->toBe([
            '2027-H1 2027-04-01..2027-06-30',
            '2027-H2 2027-07-01..2027-12-31',
            '2028-H1 2028-01-01..2028-06-30',
            '2028-H2 2028-07-01..2028-12-31',
        ])
        ->and($plan->upcoming[0]->isShortened())->toBeTrue()
        ->and($plan->upcoming[1]->isShortened())->toBeFalse();
});

it('keeps an annual change from the second quarter shortened to the rest of the year', function (): void {
    $current = PeriodWindow::containing(PeriodCadence::Quarterly, riyadhDay('2027-05-20'));
    $plan = PeriodPlanner::plan(plannerSettings(PeriodCadence::Annual), riyadhDay('2027-05-20'), $current->endsOn);

    expect(windowSpans(array_slice($plan->upcoming, 0, 2)))->toBe(['2027 2027-07-01..2027-12-31', '2028 2028-01-01..2028-12-31'])
        ->and($plan->upcoming[0]->isShortened())->toBeTrue();
});

it('plans nothing before a rollout date exists and only upcoming periods before the rollout', function (): void {
    expect(PeriodPlanner::plan(plannerSettings(PeriodCadence::Quarterly, null), riyadhDay('2026-11-20'), null)->upcoming)->toBe([]);

    $plan = PeriodPlanner::plan(plannerSettings(PeriodCadence::Quarterly), riyadhDay('2026-11-20'), null);

    expect($plan->started)->toBe([])
        ->and(windowSpans($plan->upcoming)[0])->toBe('2027-Q1 2027-01-01..2027-03-31');
});

it('fills the started periods from the rollout up to today when the schedule ran late', function (): void {
    $plan = PeriodPlanner::plan(plannerSettings(PeriodCadence::Quarterly), riyadhDay('2027-08-02'), null);

    expect(windowSpans($plan->started))->toBe(['2027-Q1 2027-01-01..2027-03-31', '2027-Q2 2027-04-01..2027-06-30', '2027-Q3 2027-07-01..2027-09-30'])
        ->and(windowSpans($plan->upcoming)[0])->toBe('2027-Q4 2027-10-01..2027-12-31');
});

it('refuses a window that ends before it starts', function (): void {
    expect(fn (): PeriodWindow => new PeriodWindow(PeriodCadence::Quarterly, 2027, 1, riyadhDay('2027-03-31'), riyadhDay('2027-01-01')))
        ->toThrow(InvalidArgumentException::class);
});
