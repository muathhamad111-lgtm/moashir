<?php

declare(strict_types=1);

use App\Enums\Finance\PaymentPurpose;
use App\Models\Finance\Payment;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

const ENTITY_BOARD_QUERY_BOUND = 55;

const EMPTY_CONTEXT = ['assessment_id' => null, 'payment_id' => null, 'message_id' => null, 'reference' => null, 'remaining_subs' => null, 'percent' => null];

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    Date::setTestNow('2026-11-03 08:00:00');
});

it('describes the single request, the single order and the profile completion of the entity items', function (): void {
    publishedSelfCriteria();
    $entity = entityWithOwner(static fn ($factory) => $factory->incompleteProfile());
    $owner = ownerOf($entity);
    $draft = startedSelfAssessment($owner);
    $payment = Payment::factory()->forEntity($entity)->create();

    $items = actionItems(boardOf($owner));
    $percent = switchTo($owner)->getJson('/api/v1/entities/me')->assertOk()->json('data.profile_completion.percent');

    expect($items->get('complete_profile')['context'] ?? null)->toBe([...EMPTY_CONTEXT, 'percent' => 80])
        ->and($percent)->toBe(80)
        ->and($items->get('submit_self_draft')['context'] ?? null)->toBe([...EMPTY_CONTEXT, 'assessment_id' => $draft->id, 'reference' => $draft->request_number])
        ->and($items->get('complete_payment')['context'] ?? null)->toBe([...EMPTY_CONTEXT, 'payment_id' => $payment->public_id, 'reference' => $payment->reference]);
});

it('gives the single accredited draft its reference and the number of remaining sub-criteria', function (): void {
    publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();

    $item = actionItems(boardOf(ownerOf($assessment->entity)))->get('submit_accredited_draft');

    expect($item['link'] ?? null)->toBe('/assessment/accredited')
        ->and($item['context'] ?? null)->toBe([...EMPTY_CONTEXT, 'assessment_id' => $assessment->id, 'reference' => $assessment->request_number, 'remaining_subs' => 2]);
});

it('gives the returned accredited request its reference', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => 'يرجى رفع وثيقة السياسة المعتمدة الموقعة.',
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'evidence']],
        'deadline_at' => '2026-11-10T08:00:00+00:00',
        'expiry_outcome' => 'continue_as_submitted',
    ])->assertOk();

    $item = actionItems(boardOf(ownerOf($assessment->entity)))->get('complete_accredited_request');

    expect($item['context'] ?? null)->toBe([...EMPTY_CONTEXT, 'assessment_id' => $assessment->id, 'reference' => $assessment->request_number]);
});

it('keeps only the empty keys when an item counts more than one record', function (): void {
    $entity = entityWithOwner();
    Payment::factory()->forEntity($entity)->create();
    Payment::factory()->forEntity($entity)->create(['purpose' => PaymentPurpose::Bag]);

    $item = actionItems(boardOf(ownerOf($entity)))->get('complete_payment');

    expect($item['count'] ?? null)->toBe(2)
        ->and($item['context'] ?? null)->toBe(EMPTY_CONTEXT);
});

it('sends no context on staff items', function (): void {
    $version = publishedAccreditedCriteria();
    submittedAccreditedRequest($version);

    switchTo(requestFollower())->getJson('/api/v1/dashboard/action-center')->assertOk()
        ->assertJsonPath('data.0.key', 'assign_requests')
        ->assertJsonPath('data.0.context', null);
});

it('keeps the entity board within the query bound with an accredited draft to describe', function (): void {
    publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    Payment::factory()->forEntity($assessment->entity)->create(['purpose' => PaymentPurpose::Bag]);

    expect(dashboardQueryCount($owner, 'entity'))->toBeLessThanOrEqual(ENTITY_BOARD_QUERY_BOUND);
});
