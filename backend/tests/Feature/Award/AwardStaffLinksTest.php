<?php

declare(strict_types=1);

use App\Enums\Award\AwardCycleStatus;
use App\Enums\Notifications\NotificationCode;
use App\Events\Award\NationalResultsApproved;
use App\Listeners\Award\NotifyAwardResultsApproved;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

// Award staff UX phase 1 (operator 2026-11-22): links open the item, cards carry their filter, and the secretary
// sees every step that waits for them. Navigation only: no authorization or visibility rule changes here.

const LINKS_DISCUSSION_OUTCOME = 'اتفقت اللجنة على اعتماد المتوسط كما هو مع التوصية بتحسين توثيق الشواهد.';

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-03 08:00:00');
});

function noticeLinkOf(User $user, NotificationCode $code): ?string
{
    $links = collect(Notification::sent($user, EntityNotification::class))
        ->filter(static fn (EntityNotification $notification): bool => $notification->notice->code === $code)
        ->map(static fn (EntityNotification $notification): ?string => $notification->notice->link);

    return $links->last();
}

/**
 * @return Collection<string, string|null>
 */
function cardLinks(TestResponse $board): Collection
{
    return collect($board->json('data.cards'))->mapWithKeys(static fn (array $card): array => [$card['key'] => $card['link'] ?? null]);
}

it('opens each committee assessor own evaluation from a return-to-committee notice', function (): void {
    $scenario = committeeScenario(1, 2);
    $assessment = $scenario['requests'][0];
    [$first, $second] = $scenario['assessors'];
    submitCommitteeEvaluation($first, $assessment, $scenario['version'])->assertOk();
    submitCommitteeEvaluation($second, $assessment, $scenario['version'])->assertOk();

    switchTo($scenario['secretary'])->postJson(secretaryPath($scenario['cycle'], $assessment, 'return'), ['general_note' => COMMITTEE_RETURN_NOTE])->assertOk();

    expect(noticeLinkOf($first, NotificationCode::ReturnedToCommittee))->toBe('/admin/committee/assignments/'.committeeRowOf($first, $assessment)->id)
        ->and(noticeLinkOf($second, NotificationCode::ReturnedToCommittee))->toBe('/admin/committee/assignments/'.committeeRowOf($second, $assessment)->id);
});

it('opens the national board from the national result notices', function (): void {
    $scenario = committeeScenario(1, 1);
    $cycle = $scenario['cycle'];

    app(NotifyAwardResultsApproved::class)->handle(new NationalResultsApproved($cycle, systemAdmin()->id));

    expect(noticeLinkOf($scenario['secretary'], NotificationCode::NationalResultApproved))->toBe("/admin/award/cycles/{$cycle->id}/results/national");
});

it('links the secretary, filtering and follower cards to the list they count, filtered', function (): void {
    $scenario = committeeScenario(1, 1);

    expect(cardLinks(boardOf($scenario['secretary'], 'award_secretary'))->all())->toMatchArray([
        'not_qualified' => '/admin/award/filtering?status=not_qualified',
        'in_committee' => '/admin/award/secretary?status=under_committee_assessment',
        'awaiting_approval' => '/admin/award/secretary?status=awaiting_secretary_approval',
    ]);
    expect(cardLinks(boardOf($scenario['officer'], 'filtering_officer'))->all())->toMatchArray([
        'awaiting_filtering' => '/admin/award/filtering?status=awaiting_filtering',
        'qualified' => '/admin/award/filtering?status=qualified_for_committee',
        'not_qualified' => '/admin/award/filtering?status=not_qualified',
        'override_decisions' => '/admin/award/filtering',
    ]);
    expect(urldecode((string) cardLinks(boardOf(requestFollower(), 'request_follower'))->get('completed')))
        ->toContain('status=completed,awaiting_filtering', 'qualified_for_committee', 'not_qualified');
});

it('lists the open discussions, the results to rank and the announcement among the secretary action items', function (): void {
    $scenario = committeeScenario(2, 1);
    [$discussed, $approved] = $scenario['requests'];
    [$assessor] = $scenario['assessors'];
    submitCommitteeEvaluation($assessor, $discussed, $scenario['version'])->assertOk();
    switchTo($scenario['secretary'])->postJson(secretaryPath($scenario['cycle'], $discussed, 'discussions'), ['mode' => 'agreed_outcome', 'agreed_outcome' => LINKS_DISCUSSION_OUTCOME])->assertCreated();
    approvedByCommittee($scenario, $approved);

    $items = actionItems($scenario['secretary']);
    expect($items->get('close_committee_discussions'))->toMatchArray(['count' => 1, 'link' => '/admin/award/secretary?status=committee_discussion'])
        ->and($items->get('rank_approved_results'))->toMatchArray(['count' => 1, 'link' => '/admin/award/results'])
        ->and($items->has('announce_results'))->toBeFalse()
        ->and(actionItems(awardSecretary())->has('close_committee_discussions'))->toBeFalse();

    $scenario['cycle']->forceFill(['status' => AwardCycleStatus::Approved, 'national_approved_at' => Date::now()])->save();

    expect(actionItems($scenario['secretary'])->get('announce_results'))->toMatchArray(['count' => 1, 'link' => '/admin/award/results']);
});

it('names the cycle and its year on the advanced rows of the follower queue', function (): void {
    $scenario = committeeScenario(1, 1);

    switchTo(requestFollower())->getJson('/api/v1/requests?filter[path]=advanced')->assertOk()
        ->assertJsonPath('data.0.award_cycle.id', $scenario['cycle']->id)
        ->assertJsonPath('data.0.award_cycle.year', $scenario['cycle']->year);
});
