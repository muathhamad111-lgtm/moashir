<?php

declare(strict_types=1);

use App\Enums\Entities\PrivacyRequestKind;
use App\Enums\Identity\EmailChangePolicy;
use App\Enums\Identity\EmailChangeStatus;
use App\Enums\Identity\RoleKey;
use App\Models\Cms\CmsPage;
use App\Models\Entities\PrivacyRequest;
use App\Models\Finance\Payment;
use App\Models\Identity\EmailChangeRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

const ACTION_CENTER = '/api/v1/dashboard/action-center';

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    Date::setTestNow('2026-11-03 08:00:00');
});

it('lists the entity\'s own pending work with deadlines, most severe first', function (): void {
    publishedSelfCriteria();
    $entity = entityWithOwner(static fn ($factory) => $factory->incompleteProfile());
    $owner = ownerOf($entity);
    startedSelfAssessment($owner);
    $payment = Payment::factory()->forEntity($entity)->create();
    Payment::factory()->forEntity(entityWithOwner())->create();

    $response = boardOf($owner);
    $items = actionItems($response);

    expect($items->keys()->all())->toBe(['complete_payment', 'complete_profile', 'submit_self_draft'])
        ->and($items->get('complete_payment'))->toBe([
            'key' => 'complete_payment',
            'count' => 1,
            'severity' => 'warning',
            'link' => '/payments',
            'due_at' => $payment->expires_at->toIso8601String(),
            'context' => ['assessment_id' => null, 'payment_id' => $payment->public_id, 'message_id' => null, 'reference' => $payment->reference, 'remaining_subs' => null, 'percent' => null],
        ])
        ->and($items->get('submit_self_draft')['link'] ?? null)->toBe('/assessment/self');
});

it('asks the entity to complete a returned accredited request before its deadline', function (): void {
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

    expect($item)->toMatchArray(['count' => 1, 'severity' => 'danger', 'link' => '/assessment/accredited', 'due_at' => '2026-11-10T08:00:00+00:00']);
});

it('gives followers the assignment and overdue queues and assessors their own reviews only', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $other = assessorUser();
    $assigned = assignedAccreditedRequest($version, $assessor);
    submittedAccreditedRequest($version);
    $assigned->forceFill(['due_at' => Date::now()->subDay()])->save();

    $follower = actionItems(boardOf(requestFollower()));
    expect($follower->keys()->all())->toBe(['follow_up_overdue_requests', 'assign_requests'])
        ->and($follower->get('assign_requests')['count'] ?? null)->toBe(1);

    $mine = actionItems(boardOf($assessor));
    expect($mine->keys()->all())->toBe(['overdue_reviews', 'start_reviews'])
        ->and($mine->get('start_reviews')['link'] ?? null)->toBe('/admin/requests/assigned')
        ->and(actionItems(boardOf($other))->all())->toBe([]);
});

it('aggregates the tasks of every held role in one action center', function (): void {
    $version = publishedAccreditedCriteria();
    submittedAccreditedRequest($version);
    CmsPage::query()->firstOrFail()->forceFill(['has_draft_changes' => true])->save();

    $user = staffUser(RoleKey::RequestFollower, RoleKey::ContentOfficer);

    expect(actionItems(boardOf($user, 'content_officer'))->keys()->all())->toBe(['assign_requests', 'publish_content_changes'])
        ->and(actionItems(boardOf(contentOfficer()))->keys()->all())->toBe(['publish_content_changes']);
});

it('shows administrative queues only to the holders of their permissions', function (): void {
    $entity = entityWithOwner();
    EmailChangeRequest::query()->create([
        'user_id' => ownerOf($entity)->id,
        'entity_id' => $entity->id,
        'old_email' => 'old@example.sa',
        'new_email' => 'new@example.sa',
        'policy' => EmailChangePolicy::RequiresApproval,
        'status' => EmailChangeStatus::AwaitingApproval,
    ]);
    PrivacyRequest::query()->create([
        'entity_id' => $entity->id,
        'kind' => PrivacyRequestKind::Access,
        'details' => 'طلب نسخة من البيانات الشخصية المحفوظة لدى المنصة.',
        'created_by_user_id' => systemAdmin()->id,
    ]);
    draftVersion()->forceFill(['validation_errors' => [['code' => 'weights_mismatch']]])->save();

    expect(actionItems(boardOf(systemAdmin()))->keys()->all())->toContain('review_entity_email_changes', 'handle_privacy_requests')
        ->and(actionItems(boardOf(criteriaOfficer()))->keys()->all())->toBe(['fix_criteria_draft'])
        ->and(actionItems(boardOf(staffUser(RoleKey::FinanceOfficer)))->all())->toBe([]);
});

it('asks finance to record a refund for a paid accredited payment that opened no request', function (): void {
    publishedAccreditedCriteria();
    storedPaidPayment()->forceFill(['paid_at' => Date::now()->subHour()])->save();
    storedPaidPayment();
    openedAccreditedRequest();

    $finance = actionItems(boardOf(staffUser(RoleKey::FinanceOfficer)));

    expect($finance->get('record_refunds'))->toMatchArray(['count' => 1, 'severity' => 'danger', 'link' => '/finance/transactions?status=paid'])
        ->and(actionItems(boardOf(contentOfficer()))->has('record_refunds'))->toBeFalse();
});

it('reminds committee assessors of pending evaluations and secretaries of results to approve', function (): void {
    $scenario = committeeScenario(1, 1);
    [$seated] = $scenario['assessors'];

    expect(actionItems(boardOf($seated))->get('complete_committee_evaluations'))->toMatchArray(['count' => 1, 'link' => '/admin/committee/assignments']);

    submitCommitteeEvaluation($seated, $scenario['requests'][0], $scenario['version'])->assertOk();

    expect(actionItems(boardOf($seated))->has('complete_committee_evaluations'))->toBeFalse()
        ->and(actionItems(boardOf($scenario['secretary']))->get('approve_committee_results'))->toMatchArray(['count' => 1, 'link' => '/admin/award/secretary']);
});

it('serves the action center on its own with the total of the counts', function (): void {
    $version = publishedAccreditedCriteria();
    submittedAccreditedRequest($version);
    submittedAccreditedRequest($version);

    switchTo(requestFollower())->getJson(ACTION_CENTER)
        ->assertOk()
        ->assertJsonPath('data.0.key', 'assign_requests')
        ->assertJsonPath('data.0.count', 2)
        ->assertJsonPath('meta.total', 2);
});
