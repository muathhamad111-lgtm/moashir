<?php

declare(strict_types=1);

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\AssignmentStatus;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Criteria\CriteriaVersion;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

// Admin review 2026-11-20 (R1): one request list with stage filters instead of the queue and the preparing tab,
// and the assessor kept on the request in every state.
const REQUEST_QUEUE = '/api/v1/requests';

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
    publishedAccreditedCriteria();
});

function stageQueueIds(string $query = ''): array
{
    return collect(test()->getJson(REQUEST_QUEUE.$query)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
}

function sortedRequestIds(int ...$ids): array
{
    return collect($ids)->sort()->values()->all();
}

it('lists the drafts with the submitted requests and narrows them by stage', function (): void {
    $version = CriteriaVersion::query()->published()->firstOrFail();
    $draft = openedAccreditedRequest();
    $frozen = openedAccreditedRequest();
    $frozen->entity->delete();
    $submitted = submittedAccreditedRequest($version);
    $reviewing = reviewedAccreditedRequest($version, assessorUser());
    $completed = completedAccreditedRequest($version, assessorUser());
    $refunded = submittedAccreditedRequest($version);
    $refunded->forceFill(['status' => AccreditedAssessmentStatus::Refunded, 'refunded_at' => Date::now()])->save();
    $late = submittedAccreditedRequest($version);
    $late->forceFill(['due_at' => Date::now()->subDay()])->save();

    switchTo(requestFollower());

    expect(stageQueueIds())->toBe(sortedRequestIds($draft->id, $submitted->id, $reviewing->id, $completed->id, $refunded->id, $late->id))
        ->and(stageQueueIds('?filter[stage]=all'))->toBe(stageQueueIds())
        ->and(stageQueueIds('?filter[stage]=preparing'))->toBe([$draft->id])
        ->and(stageQueueIds('?filter[stage]=awaiting_assignment'))->toBe(sortedRequestIds($submitted->id, $late->id))
        ->and(stageQueueIds('?filter[stage]=in_progress'))->toBe([$reviewing->id])
        ->and(stageQueueIds('?filter[stage]=overdue'))->toBe([$late->id])
        ->and(stageQueueIds('?filter[stage]=closed'))->toBe(sortedRequestIds($completed->id, $refunded->id));
});

it('counts every stage whichever stage is shown', function (): void {
    $version = CriteriaVersion::query()->published()->firstOrFail();
    openedAccreditedRequest();
    openedAccreditedRequest();
    submittedAccreditedRequest($version);
    reviewedAccreditedRequest($version, assessorUser());
    completedAccreditedRequest($version, assessorUser());

    $stats = switchTo(requestFollower())->getJson(REQUEST_QUEUE.'?filter[stage]=closed')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.path_counts.0.preparing', 2)
        ->json('meta.stats');

    expect($stats['total'])->toBe(5)
        ->and($stats['by_stage'])->toBe(['preparing' => 2, 'awaiting_assignment' => 1, 'in_progress' => 1, 'closed' => 1, 'overdue' => 0])
        ->and($stats['by_status']['draft'])->toBe(2);
});

it('gives a draft row the entity progress, the opening date and the last entity activity', function (): void {
    $version = CriteriaVersion::query()->published()->firstOrFail();
    $draft = openedAccreditedRequest();
    $started = readyAccreditedDraft($version);
    $submitted = submittedAccreditedRequest($version);

    $rows = collect(switchTo(requestFollower())->getJson(REQUEST_QUEUE)->assertOk()->json('data'))->keyBy('id');

    expect($rows[$draft->id]['status'])->toBe('draft')
        ->and($rows[$draft->id]['opened_at'])->toBe($draft->created_at->toIso8601String())
        ->and($rows[$draft->id]['progress'])->toBe(['subs_complete' => 1, 'subs_total' => 3])
        ->and($rows[$draft->id]['assessor'])->toBeNull()
        ->and($rows[$started->id]['progress'])->toBe(['subs_complete' => 3, 'subs_total' => 3])
        ->and(Date::parse($rows[$started->id]['last_activity_at'])->greaterThanOrEqualTo($started->created_at))->toBeTrue()
        ->and($rows[$submitted->id]['progress'])->toBeNull();
});

it('gives every row the payment status only, and the payment details to finance holders only', function (): void {
    $draft = openedAccreditedRequest();
    $payment = $draft->payment()->with('receipt')->firstOrFail();

    $row = switchTo(requestFollower())->getJson(REQUEST_QUEUE.'?filter[stage]=preparing')->assertOk()->json('data.0');
    expect($row['payment'])->toBe(['status' => 'paid', 'is_test' => $payment->isTest()]);

    $admin = switchTo(systemAdmin())->getJson(REQUEST_QUEUE.'?filter[stage]=preparing')->assertOk()->json('data.0.payment');
    expect($admin['id'])->toBe($payment->public_id)
        ->and($admin['reference'])->toBe($payment->reference)
        ->and($admin['channel'])->toBe($payment->channel->value)
        ->and($admin['paid_at'])->toBe($payment->paid_at?->toIso8601String())
        ->and($admin['receipt']['number'])->toBe($payment->receipt?->number)
        ->and($admin['receipt']['id'])->toBe($payment->receipt?->public_id);
});

it('sorts a draft by its opening date among the submitted requests', function (): void {
    $version = CriteriaVersion::query()->published()->firstOrFail();
    $older = submittedAccreditedRequest($version);
    $older->forceFill(['submitted_at' => Date::now()->subDays(5)])->save();
    $draft = openedAccreditedRequest();
    $draft->forceFill(['created_at' => Date::now()->subDays(3)])->save();
    $newer = submittedAccreditedRequest($version);

    switchTo(requestFollower());

    expect(collect(test()->getJson(REQUEST_QUEUE)->assertOk()->json('data'))->pluck('id')->all())->toBe([$newer->id, $draft->id, $older->id])
        ->and(collect(test()->getJson(REQUEST_QUEUE.'?sort=submitted_at')->json('data'))->pluck('id')->all())->toBe([$older->id, $draft->id, $newer->id])
        ->and(collect(test()->getJson(REQUEST_QUEUE.'?sort=due_at')->json('data'))->pluck('id')->last())->toBe($draft->id)
        ->and(collect(test()->getJson(REQUEST_QUEUE.'?sort=-due_at')->json('data'))->pluck('id')->last())->toBe($draft->id);
    test()->getJson(REQUEST_QUEUE.'?search='.$draft->request_number)->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $draft->id);
});

it('keeps the assessor on the request after the review is completed', function (): void {
    $version = CriteriaVersion::query()->published()->firstOrFail();
    $assessor = assessorUser();
    $completed = completedAccreditedRequest($version, $assessor);

    expect(AssessmentAssignment::query()->where('assessment_id', $completed->id)->value('status'))->toBe(AssignmentStatus::Completed);

    switchTo(requestFollower())->getJson(REQUEST_QUEUE.'?filter[stage]=closed')->assertOk()
        ->assertJsonPath('data.0.assessor.id', $assessor->id)
        ->assertJsonPath('data.0.assessor.name', $assessor->name)
        ->assertJsonPath('data.0.is_reassigned', false);
    test()->getJson(REQUEST_QUEUE."/{$completed->id}")->assertOk()
        ->assertJsonPath('data.assessor.id', $assessor->id)
        ->assertJsonPath('data.is_reassigned', false);
});

it('keeps the assessor on a request whose assignment ended with a refund', function (): void {
    $version = CriteriaVersion::query()->published()->firstOrFail();
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest($version, $assessor);
    AssessmentAssignment::query()->where('assessment_id', $assessment->id)->firstOrFail()
        ->forceFill(['status' => AssignmentStatus::Ended, 'ended_at' => Date::now(), 'end_reason' => 'refund'])->save();
    $assessment->forceFill(['status' => AccreditedAssessmentStatus::Refunded, 'refunded_at' => Date::now()])->save();

    switchTo(requestFollower())->getJson(REQUEST_QUEUE.'?filter[stage]=closed')->assertOk()
        ->assertJsonPath('data.0.status', 'refunded')
        ->assertJsonPath('data.0.assessor.id', $assessor->id);
});

it('shows the latest assessor of a reassigned request and marks it as reassigned', function (): void {
    $version = CriteriaVersion::query()->published()->firstOrFail();
    $first = assessorUser();
    $second = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $first);
    $untouched = assignedAccreditedRequest($version, assessorUser());

    switchTo(requestFollower())->postJson(REQUEST_QUEUE."/{$assessment->id}/reassign", ['assessor_id' => $second->id, 'reason' => ASSIGNMENT_REASON])->assertOk()
        ->assertJsonPath('data.assessor.id', $second->id)
        ->assertJsonPath('data.is_reassigned', true);

    $rows = collect(test()->getJson(REQUEST_QUEUE)->assertOk()->json('data'))->keyBy('id');

    expect($rows[$assessment->id]['assessor']['id'])->toBe($second->id)
        ->and($rows[$assessment->id]['is_reassigned'])->toBeTrue()
        ->and($rows[$untouched->id]['is_reassigned'])->toBeFalse();
    test()->getJson(REQUEST_QUEUE."/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.assessor.id', $second->id)
        ->assertJsonPath('data.is_reassigned', true);
});

it('filters the drafts by the period they were opened in and the submitted requests by their period', function (): void {
    atRiyadh('2027-02-01 10:00');
    livePeriods();
    atRiyadh('2026-12-20 10:00');
    $early = openedAccreditedRequest();
    atRiyadh('2027-02-01 10:00');
    $current = openedAccreditedRequest();
    $q1 = periodByKey('2027-Q1')->id;

    switchTo(requestFollower());

    expect(stageQueueIds("?filter[stage]=preparing&filter[period_id]={$q1}"))->toBe([$current->id])
        ->and(stageQueueIds("?filter[period_id]={$q1}"))->toBe([$current->id])
        ->and(stageQueueIds('?filter[stage]=preparing&filter[period_id]=none'))->toBe([$early->id]);
});

it('keeps the request list to the request queue followers', function (RoleKey $role, int $status): void {
    openedAccreditedRequest();

    switchTo(staffUser($role))->getJson(REQUEST_QUEUE.'?filter[stage]=preparing')->assertStatus($status);
})->with([
    'system administrator' => [RoleKey::SystemAdmin, 200],
    'requests follower' => [RoleKey::RequestFollower, 200],
    'finance officer' => [RoleKey::FinanceOfficer, 403],
    'assessor' => [RoleKey::Assessor, 403],
]);

it('refuses the request list to entities and guests', function (): void {
    $draft = openedAccreditedRequest();

    switchTo(ownerOf($draft->entity))->getJson(REQUEST_QUEUE.'?filter[stage]=preparing')->assertForbidden();
    auth()->forgetGuards();
    test()->getJson(REQUEST_QUEUE)->assertUnauthorized();
});

it('validates the stage filter', function (string $query): void {
    switchTo(requestFollower())->getJson(REQUEST_QUEUE.'?'.$query)->assertUnprocessable();
})->with([
    'unknown stage' => ['filter[stage]=archived'],
    'stage as a list' => ['filter[stage][]=preparing'],
    'state outside the list' => ['filter[status]=awaiting_payment'],
]);

it('scopes the drafts to the paths a follower follows', function (): void {
    openedAccreditedRequest();

    switchTo(followerOfPaths(AssessmentPath::Advanced))->getJson(REQUEST_QUEUE.'?filter[stage]=preparing')->assertOk()
        ->assertJsonPath('meta.path', 'advanced')
        ->assertJsonPath('meta.pagination.total', 0);
});

it('no longer serves the separate preparing list', function (): void {
    switchTo(requestFollower())->getJson(REQUEST_QUEUE.'/preparing')->assertNotFound();
});

it('never lets a paid draft be assigned', function (): void {
    $draft = openedAccreditedRequest();

    switchTo(requestFollower())->postJson(REQUEST_QUEUE."/{$draft->id}/assign", ['assessor_id' => assessorUser()->id])
        ->assertConflict()->assertJsonPath('code', 'state.transition_illegal');

    expect($draft->refresh()->status->value)->toBe('draft');
});

it('shows the payment details on the request detail to finance viewers and only the paid flag to others', function (): void {
    $version = CriteriaVersion::query()->published()->firstOrFail();
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest($version, $assessor);
    $payment = $assessment->payment()->with('receipt')->firstOrFail();

    $admin = switchTo(systemAdmin())->getJson(REQUEST_QUEUE."/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.payment.paid', true)
        ->assertJsonPath('data.payment.reference', $payment->reference)
        ->assertJsonPath('data.payment.status', 'paid')
        ->assertJsonPath('data.payment.receipt.number', $payment->receipt?->number);
    expect($admin->json('data.payment.paid_at'))->toBe($payment->paid_at?->toIso8601String());

    expect(switchTo(requestFollower())->getJson(REQUEST_QUEUE."/{$assessment->id}")->assertOk()->json('data.payment'))->toBe(['paid' => true])
        ->and(switchTo($assessor)->getJson(REQUEST_QUEUE."/{$assessment->id}")->assertOk()->json('data.payment'))->toBe(['paid' => true]);
});
