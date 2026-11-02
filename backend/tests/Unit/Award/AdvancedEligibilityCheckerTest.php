<?php

declare(strict_types=1);

use App\Data\Award\CycleSchedule;
use App\Enums\Award\AwardCycleStatus;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Entities\Region;
use App\Services\Award\AdvancedEligibilityChecker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

function eligibilityCycle(Region $region, AwardCycleStatus $status = AwardCycleStatus::SubmissionOpen): AwardCycle
{
    $cycle = new AwardCycle([
        'status' => $status,
        'submission_start' => CarbonImmutable::parse('2026-10-01 00:00:00'),
        'submission_end' => CarbonImmutable::parse('2026-11-30 00:00:00'),
        'min_entity_age_years' => 3,
    ]);

    return $cycle->setRelation('regions', Region::query()->whereKey($region->id)->get());
}

it('compares the age of the entity in the Riyadh year with the cycle minimum', function (int $foundingYear, bool $passed): void {
    $region = Region::factory()->create();
    $entity = new Entity(['founding_year' => $foundingYear, 'region_id' => $region->id]);

    $eligibility = app(AdvancedEligibilityChecker::class)->check($entity, eligibilityCycle($region), Date::parse('2026-11-01 08:00:00'));

    expect($eligibility->checks[0]->passed)->toBe($passed)
        ->and($eligibility->checks[0]->actual)->toBe(2026 - $foundingYear)
        ->and($eligibility->isEligible())->toBe($passed);
})->with([
    'exactly the minimum age' => [2023, true],
    'one year short' => [2024, false],
    'founded this year' => [2026, false],
    'long established' => [1995, true],
]);

it('uses the Riyadh calendar year at the turn of the year', function (): void {
    $entity = new Entity(['founding_year' => 2024]);

    expect(app(AdvancedEligibilityChecker::class)->entityAgeAt($entity, Date::parse('2026-12-31 21:30:00')))->toBe(3)
        ->and(app(AdvancedEligibilityChecker::class)->entityAgeAt($entity, Date::parse('2026-12-31 20:30:00')))->toBe(2);
});

it('requires a region of the cycle, an open window and a running cycle', function (): void {
    $covered = Region::factory()->create();
    $other = Region::factory()->create();
    $checker = app(AdvancedEligibilityChecker::class);
    $now = Date::parse('2026-11-01 08:00:00');

    $outside = $checker->check(new Entity(['founding_year' => 2010, 'region_id' => $other->id]), eligibilityCycle($covered), $now);
    $closedWindow = $checker->check(new Entity(['founding_year' => 2010, 'region_id' => $covered->id]), eligibilityCycle($covered, AwardCycleStatus::SubmissionClosed), $now);
    $noCycle = $checker->check(new Entity(['founding_year' => 2010, 'region_id' => $covered->id]), null, $now);

    expect($outside->failuresToArray()[0]['rule'])->toBe('region')
        ->and($closedWindow->meetsConditions())->toBeTrue()
        ->and($closedWindow->windowOpen)->toBeFalse()
        ->and($closedWindow->isEligible())->toBeFalse()
        ->and($noCycle->toArray())->toBe(['eligible' => false, 'window_open' => false, 'conditions' => []]);
});

it('orders the cycle dates', function (): void {
    $schedule = new CycleSchedule(
        CarbonImmutable::parse('2026-11-01'),
        CarbonImmutable::parse('2026-11-30'),
        CarbonImmutable::parse('2026-11-30'),
        CarbonImmutable::parse('2026-12-30'),
        null,
    );

    expect($schedule->isOrdered())->toBeTrue()
        ->and($schedule->with(['committee_start' => CarbonImmutable::parse('2026-11-29')])->isOrdered())->toBeFalse()
        ->and($schedule->with(['announcement_at' => CarbonImmutable::parse('2026-12-29')])->isOrdered())->toBeFalse()
        ->and($schedule->with(['announcement_at' => CarbonImmutable::parse('2027-01-05')])->isOrdered())->toBeTrue()
        ->and(CycleSchedule::from(['submission_start' => CarbonImmutable::parse('2026-11-01')]))->toBeNull();
});
