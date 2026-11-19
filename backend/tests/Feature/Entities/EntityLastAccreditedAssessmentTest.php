<?php

declare(strict_types=1);

use App\Enums\Criteria\CriteriaVersionStatus;
use App\Enums\Identity\RoleKey;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

const LAST_ASSESSMENT_LIST = '/api/v1/entities';

beforeEach(function (): void {
    Notification::fake();
});

function lastAssessmentCriteria(): CriteriaVersion
{
    return CriteriaVersion::query()->where('status', CriteriaVersionStatus::Published)->first() ?? insightsCriteria();
}

function accreditedOn(Entity $entity, string $percent, string $completedAt): void
{
    accreditedResultFor($entity, lastAssessmentCriteria(), array_fill_keys(['M1.1', 'M1.2', 'M2.1', 'M2.2'], $percent), $completedAt);
}

/**
 * @return list<int>
 */
function entityIdsSortedBy(string $sort): array
{
    return collect(switchTo(systemAdmin())->getJson(LAST_ASSESSMENT_LIST.'?per_page=50&sort='.$sort)->assertOk()->json('data'))->pluck('id')->all();
}

it('shows the percent and date of the latest completed accredited assessment, never a self-assessment', function (): void {
    $assessed = entityWithOwner();
    accreditedOn($assessed, '65.00', '2026-06-01 09:00:00');
    accreditedOn($assessed, '85.00', '2026-08-01 09:00:00');
    selfResultFor($assessed, lastAssessmentCriteria())->forceFill(['completed_at' => CarbonImmutable::parse('2026-09-01 09:00:00')])->save();
    $never = entityWithOwner();

    $rows = collect(switchTo(systemAdmin())->getJson(LAST_ASSESSMENT_LIST.'?per_page=50')->assertOk()->json('data'))->keyBy('id');

    expect($rows[$assessed->id]['last_accredited_assessment'])->toBe(['percent' => '85.00', 'completed_at' => '2026-08-01T09:00:00+00:00'])
        ->and($rows[$never->id]['last_accredited_assessment'])->toBeNull();
    switchTo(systemAdmin())->getJson(LAST_ASSESSMENT_LIST.'/'.$assessed->id)->assertOk()
        ->assertJsonPath('data.last_accredited_assessment.percent', '85.00');
});

it('sorts by the last assessment date or percent both ways, entities never assessed last', function (string $sort, array $order): void {
    $entities = ['early_high' => entityWithOwner(), 'late_low' => entityWithOwner(), 'never' => entityWithOwner()];
    accreditedOn($entities['early_high'], '85.00', '2026-05-01 09:00:00');
    accreditedOn($entities['late_low'], '65.00', '2026-08-01 09:00:00');

    $ids = array_values(array_intersect(entityIdsSortedBy($sort), array_map(static fn (Entity $entity): int => $entity->id, $entities)));

    expect($ids)->toBe(array_map(static fn (string $name): int => $entities[$name]->id, $order));
})->with([
    'oldest first' => ['last_assessed_at', ['early_high', 'late_low', 'never']],
    'newest first' => ['-last_assessed_at', ['late_low', 'early_high', 'never']],
    'lowest first' => ['last_assessment_percent', ['late_low', 'early_high', 'never']],
    'highest first' => ['-last_assessment_percent', ['early_high', 'late_low', 'never']],
]);

it('loads the last assessment of a page with a constant number of queries', function (): void {
    $admin = systemAdmin();
    $count = static function () use ($admin): int {
        switchTo($admin)->getJson(LAST_ASSESSMENT_LIST.'?per_page=50&sort=-last_assessed_at')->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        switchTo($admin)->getJson(LAST_ASSESSMENT_LIST.'?per_page=50&sort=-last_assessed_at')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };
    accreditedOn(entityWithOwner(), '70.00', '2026-06-01 09:00:00');
    $before = $count();

    foreach (['55.00', '65.00', '75.00'] as $percent) {
        accreditedOn(entityWithOwner(), $percent, '2026-07-01 09:00:00');
    }

    expect($count())->toBe($before);
});

it('keeps the assessment result out of the name and contact view', function (): void {
    accreditedOn(entityWithOwner(), '85.00', '2026-08-01 09:00:00');

    switchTo(staffUser(RoleKey::FinanceOfficer))->getJson(LAST_ASSESSMENT_LIST)->assertOk()
        ->assertJsonMissingPath('data.0.last_accredited_assessment');
});

it('refuses an unknown sort', function (): void {
    switchTo(systemAdmin())->getJson(LAST_ASSESSMENT_LIST.'?sort=last_assessment')->assertUnprocessable()->assertJsonValidationErrors('sort');
});
