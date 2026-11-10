<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\RoleKey;
use App\Models\Criteria\CriteriaVersion;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

const PREPARING_REQUESTS = '/api/v1/requests/preparing';

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
    publishedAccreditedCriteria();
});

it('lists the paid drafts of operational entities with the payment, the progress and the last entity activity', function (): void {
    $version = CriteriaVersion::query()->published()->firstOrFail();
    $draft = openedAccreditedRequest();
    $started = readyAccreditedDraft($version);
    $frozen = openedAccreditedRequest();
    $frozen->entity->delete();
    submittedAccreditedRequest($version);

    $response = switchTo(requestFollower())->getJson(PREPARING_REQUESTS)->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.path', 'accredited');

    $rows = collect($response->json('data'))->keyBy('id');
    $payment = $draft->payment()->with('receipt')->firstOrFail();

    expect($rows->keys()->sort()->values()->all())->toBe(collect([$draft->id, $started->id])->sort()->values()->all())
        ->and($rows[$draft->id]['status'])->toBe('draft')
        ->and($rows[$draft->id]['number'])->toBe($draft->request_number)
        ->and($rows[$draft->id]['opened_at'])->toBe($draft->created_at->toIso8601String())
        ->and($rows[$draft->id]['payment']['reference'])->toBe($payment->reference)
        ->and($rows[$draft->id]['payment']['channel'])->toBe($payment->channel->value)
        ->and($rows[$draft->id]['payment']['status'])->toBe('paid')
        ->and($rows[$draft->id]['payment']['paid_at'])->toBe($payment->paid_at?->toIso8601String())
        ->and($rows[$draft->id]['payment']['receipt']['number'])->toBe($payment->receipt?->number)
        ->and($rows[$draft->id]['payment'])->not->toHaveKey('id')
        ->and($rows[$draft->id]['payment']['receipt'])->not->toHaveKey('id')
        ->and($rows[$draft->id]['progress'])->toBe(['subs_complete' => 1, 'subs_total' => 3])
        ->and($rows[$started->id]['progress'])->toBe(['subs_complete' => 3, 'subs_total' => 3])
        ->and(Date::parse($rows[$started->id]['last_activity_at'])->greaterThanOrEqualTo($started->created_at))->toBeTrue();
});

it('keeps the paid drafts out of the assignment queue and its statistics, and counts them per path', function (): void {
    $version = CriteriaVersion::query()->published()->firstOrFail();
    openedAccreditedRequest();
    openedAccreditedRequest();
    submittedAccreditedRequest($version);

    switchTo(requestFollower())->getJson('/api/v1/requests')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.stats.total', 1)
        ->assertJsonMissingPath('meta.stats.by_status.draft')
        ->assertJsonPath('meta.path_counts.0.path', 'accredited')
        ->assertJsonPath('meta.path_counts.0.total', 1)
        ->assertJsonPath('meta.path_counts.0.awaiting_assignment', 1)
        ->assertJsonPath('meta.path_counts.0.preparing', 2);

    test()->getJson(PREPARING_REQUESTS)->assertOk()->assertJsonPath('meta.path_counts.0.preparing', 2);
});

it('gives the payment and receipt identifiers only to holders of the finance permissions', function (): void {
    $draft = openedAccreditedRequest();
    $payment = $draft->payment()->with('receipt')->firstOrFail();

    $row = switchTo(systemAdmin())->getJson(PREPARING_REQUESTS)->assertOk()->json('data.0');

    expect($row['payment']['id'])->toBe($payment->public_id)
        ->and($row['payment']['receipt']['id'])->toBe($payment->receipt?->public_id);
});

it('searches the paid drafts and sorts them by opening date', function (): void {
    $older = openedAccreditedRequest();
    $older->forceFill(['created_at' => Date::now()->subDays(3)])->save();
    $newer = openedAccreditedRequest();

    $follower = requestFollower();
    switchTo($follower)->getJson(PREPARING_REQUESTS)->assertOk()->assertJsonPath('data.0.id', $older->id);
    test()->getJson(PREPARING_REQUESTS.'?sort=-created_at')->assertOk()->assertJsonPath('data.0.id', $newer->id);
    test()->getJson(PREPARING_REQUESTS.'?search='.$newer->request_number)->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $newer->id);
});

it('keeps the paid drafts to the request queue followers', function (RoleKey $role, int $status): void {
    openedAccreditedRequest();

    switchTo(staffUser($role))->getJson(PREPARING_REQUESTS)->assertStatus($status);
})->with([
    'system administrator' => [RoleKey::SystemAdmin, 200],
    'requests follower' => [RoleKey::RequestFollower, 200],
    'finance officer' => [RoleKey::FinanceOfficer, 403],
    'assessor' => [RoleKey::Assessor, 403],
]);

it('refuses the paid drafts to entities and guests', function (): void {
    $draft = openedAccreditedRequest();

    switchTo(ownerOf($draft->entity))->getJson(PREPARING_REQUESTS)->assertForbidden();
    auth()->forgetGuards();
    test()->getJson(PREPARING_REQUESTS)->assertUnauthorized();
});

it('validates the paid drafts filters', function (string $query): void {
    switchTo(requestFollower())->getJson(PREPARING_REQUESTS.'?'.$query)->assertUnprocessable();
})->with([
    'status filter' => ['filter[status]=draft'],
    'overdue filter' => ['filter[overdue]=true'],
    'unknown sort' => ['sort=-submitted_at'],
    'page size' => ['per_page=101'],
    'search length' => ['search='.str_repeat('a', 101)],
    'unfollowed path' => ['filter[path]=self'],
]);

it('scopes the paid drafts to the paths a follower follows', function (): void {
    openedAccreditedRequest();
    $advancedFollower = followerOfPaths(AssessmentPath::Advanced);

    switchTo($advancedFollower)->getJson(PREPARING_REQUESTS)->assertOk()
        ->assertJsonPath('meta.path', 'advanced')
        ->assertJsonPath('meta.pagination.total', 0);
    test()->getJson(PREPARING_REQUESTS.'?filter[path]=accredited')->assertUnprocessable();
});

it('never lets a paid draft be assigned', function (): void {
    $draft = openedAccreditedRequest();

    switchTo(requestFollower())->postJson("/api/v1/requests/{$draft->id}/assign", ['assessor_id' => assessorUser()->id])
        ->assertConflict()->assertJsonPath('code', 'state.transition_illegal');

    expect($draft->refresh()->status->value)->toBe('draft');
});

it('shows the payment card on the request detail by audience', function (): void {
    $version = CriteriaVersion::query()->published()->firstOrFail();
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest($version, $assessor);
    $payment = $assessment->payment()->with('receipt')->firstOrFail();

    $admin = switchTo(systemAdmin())->getJson("/api/v1/requests/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.payment.paid', true)
        ->assertJsonPath('data.payment.reference', $payment->reference)
        ->assertJsonPath('data.payment.channel', $payment->channel->value)
        ->assertJsonPath('data.payment.status', 'paid')
        ->assertJsonPath('data.payment.id', $payment->public_id)
        ->assertJsonPath('data.payment.receipt.number', $payment->receipt?->number)
        ->assertJsonPath('data.payment.receipt.id', $payment->receipt?->public_id);
    expect($admin->json('data.payment.paid_at'))->toBe($payment->paid_at?->toIso8601String());

    $follower = switchTo(requestFollower())->getJson("/api/v1/requests/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.payment.reference', $payment->reference);
    expect($follower->json('data.payment'))->not->toHaveKey('id')
        ->and($follower->json('data.payment.receipt'))->not->toHaveKey('id');

    $own = switchTo($assessor)->getJson("/api/v1/requests/{$assessment->id}")->assertOk();
    expect($own->json('data.payment'))->toBe(['paid' => true]);
});
