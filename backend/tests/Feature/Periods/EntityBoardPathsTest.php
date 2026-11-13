<?php

declare(strict_types=1);

use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentStatus;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
});

/**
 * @return array<string, mixed>
 */
function nextStepOf(Entity $entity): ?array
{
    $data = boardWidget(boardOf(ownerOf($entity), 'entity'), 'next_step');

    return is_array($data) ? $data : null;
}

/**
 * @return array<string, mixed>
 */
function pathsOf(Entity $entity): array
{
    $data = boardWidget(boardOf(ownerOf($entity), 'entity'), 'paths');

    return is_array($data) ? $data : [];
}

it('summarises the three paths of an entity that has not started anything', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    setServicePrices('1000.00');
    $entity = entityWithOwner();

    $paths = pathsOf($entity);

    expect($paths['self'])->toBe([
        'pricing' => ['price' => null, 'is_free' => true, 'availability' => 'available'],
        'is_listed' => true,
        'awaiting_payment' => null,
        'open_draft' => null,
        'last_result' => null,
    ])
        ->and($paths['accredited']['pricing']['price']['total'])->toBe('1150.00')
        ->and($paths['accredited']['is_live'])->toBeTrue()
        ->and($paths['accredited']['cadence'])->toBe('quarterly')
        ->and($paths['accredited']['current_period']['key'])->toBe('2027-Q1')
        ->and($paths['accredited']['period_status'])->toBe('not_submitted')
        ->and($paths['accredited']['next_period']['key'])->toBe('2027-Q2')
        ->and($paths['accredited']['next_period']['notice_on'])->toBe('2027-03-18')
        ->and($paths['accredited']['next_submission_from'])->toBeNull()
        ->and($paths['accredited']['awaiting_payment'])->toBeNull()
        ->and($paths['accredited']['open_request'])->toBeNull()
        ->and($paths['accredited']['last_result'])->toBeNull()
        ->and($paths['accredited']['kit'])->toBe(['status' => null, 'request_id' => null])
        ->and($paths['award']['window_state'])->toBe('none')
        ->and($paths['award']['cycle'])->toBeNull()
        ->and($paths['award']['eligibility'])->toBe(['eligible' => false, 'reason' => 'no_cycle'])
        ->and($paths['award']['announced'])->toBeFalse()
        ->and($paths['award']['pricing']['is_free'])->toBeTrue();

    expect(nextStepOf($entity))->toBe([
        'rank' => 7,
        'key' => 'apply_accredited',
        'path' => 'accredited',
        'target' => ['route' => 'paths.accredited', 'params' => [], 'query' => [], 'href' => '/paths/accredited'],
        'context' => [
            'assessment_id' => null, 'number' => null, 'status' => null, 'payment_id' => null, 'is_paid' => null,
            'deadline_at' => null, 'period_ends_on' => '2027-03-31', 'cycle_id' => null,
            'payment_reference' => null, 'payment_channel' => null, 'payment_status' => null, 'payment_expires_at' => null,
        ],
    ]);
});

it('points to the open accredited draft first and then to the review', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $draft = readyAccreditedDraft($version);

    $step = nextStepOf($draft->entity);
    expect($step['rank'])->toBe(3)
        ->and($step['key'])->toBe('finish_draft')
        ->and($step['target'])->toBe(['route' => 'paths.accredited.request', 'params' => ['id' => $draft->id], 'query' => ['tab' => 'request'], 'href' => "/paths/accredited/{$draft->id}?tab=request"])
        ->and($step['context']['is_paid'])->toBeTrue()
        ->and($step['context']['period_ends_on'])->toBe('2027-03-31');

    $open = pathsOf($draft->entity)['accredited']['open_request'];
    expect($open)->toMatchArray(['id' => $draft->id, 'status' => 'draft', 'needs_completion' => false, 'remaining_count' => 0, 'period_state' => 'pending']);

    submitDraft($draft);
    expect(nextStepOf($draft->entity))->toMatchArray(['rank' => 4, 'key' => 'track_review'])
        ->and(pathsOf($draft->entity)['accredited']['period_status'])->toBe('in_progress')
        ->and(pathsOf($draft->entity)['accredited']['open_request']['period_label']['en'])->toBe('Q1 2027');
});

it('asks to complete a request returned for completion before anything else but a payment', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    $deadline = Date::now()->addDays(7);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => 'يرجى تحديث وصف السياسة المعتمدة وإرفاق نسختها الموقعة.',
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv']],
        'deadline_at' => $deadline->toIso8601String(),
        'expiry_outcome' => 'continue_as_submitted',
    ])->assertOk();

    $step = nextStepOf($assessment->entity);
    expect($step['rank'])->toBe(2)
        ->and($step['key'])->toBe('complete_request')
        ->and($step['context']['status'])->toBe('needs_completion')
        ->and($step['context']['deadline_at'])->not->toBeNull()
        ->and(pathsOf($assessment->entity)['accredited']['open_request']['needs_completion'])->toBeTrue();

    Payment::factory()->forEntity($assessment->entity)->create(['channel' => PaymentChannel::Electronic, 'status' => PaymentStatus::Created]);
    $settle = nextStepOf($assessment->entity);
    expect($settle['rank'])->toBe(1)
        ->and($settle['key'])->toBe('settle_payment')
        ->and($settle['context']['payment_id'])->not->toBeNull();
});

it('shows the assessed period and falls back to the self path', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $completed = completedInPeriod(publishedAccreditedCriteria(), assessorUser());
    $entity = $completed->entity;

    $accredited = pathsOf($entity)['accredited'];
    expect($accredited['period_status'])->toBe('assessed')
        ->and($accredited['next_submission_from'])->toBe('2027-04-01')
        ->and($accredited['last_result']['assessment_id'])->toBe($completed->id)
        ->and($accredited['last_result']['level'])->toBe('silver')
        ->and($accredited['last_result']['period']['key'])->toBe('2027-Q1')
        ->and($accredited['last_result']['certificate']['valid_until'])->toBe('2027-06-30')
        ->and(nextStepOf($entity))->toMatchArray(['rank' => 10, 'key' => 'start_self', 'path' => 'self']);

    $level = boardWidget(boardOf(ownerOf($entity), 'entity'), 'level');
    expect($level['level'])->toBe('silver')
        ->and($level['certificate']['valid_until'])->toBe('2027-06-30');

    $self = startedSelfAssessment(ownerOf($entity));
    $selfPath = pathsOf($entity)['self'];

    expect(nextStepOf($entity))->toMatchArray(['rank' => 9, 'key' => 'continue_self_draft'])
        ->and($selfPath['open_draft']['id'])->toBe($self->id)
        ->and($selfPath['open_draft']['progress']['subs_complete'])->toBe(0)
        ->and($selfPath['open_draft']['progress']['subs_total'])->toBeGreaterThan(0);
});

it('describes an open award cycle and invites an eligible entity before the rollout', function (): void {
    atRiyadh('2026-11-20 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $entity = cycleEntity($cycle);

    $award = pathsOf($entity)['award'];
    expect($award['window_state'])->toBe('open')
        ->and($award['cycle']['id'])->toBe($cycle->id)
        ->and($award['cycle']['name'])->toBe($cycle->name->toArray())
        ->and($award['cycle']['regions'])->toHaveCount(1)
        ->and($award['eligibility'])->toBe(['eligible' => true, 'reason' => null])
        ->and($award['open_request'])->toBeNull()
        ->and(pathsOf($entity)['accredited']['is_live'])->toBeFalse()
        ->and(nextStepOf($entity))->toMatchArray(['rank' => 8, 'key' => 'apply_award', 'path' => 'advanced'])
        ->and(nextStepOf($entity)['context']['cycle_id'])->toBe($cycle->id);

    $tooYoung = cycleEntity($cycle, (int) Date::now()->year);
    expect(pathsOf($tooYoung)['award']['eligibility'])->toBe(['eligible' => false, 'reason' => 'min_entity_age']);

    $draft = advancedDraft($cycle, $entity);
    expect(pathsOf($entity)['award']['open_request']['id'])->toBe($draft->id)
        ->and(pathsOf($entity)['award']['eligibility']['reason'])->toBe('request_exists')
        ->and(nextStepOf($entity))->toMatchArray(['rank' => 3, 'key' => 'finish_draft', 'path' => 'advanced']);

    closeCycleSubmissions($cycle);
    expect(pathsOf($entity)['award']['window_state'])->toBe('closed')
        ->and(pathsOf($entity)['award']['eligibility']['reason'])->toBe('window_closed');
});
