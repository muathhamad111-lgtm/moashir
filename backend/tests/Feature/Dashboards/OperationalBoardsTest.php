<?php

declare(strict_types=1);

use App\Enums\Cms\CmsPageKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Kpis\Kpi;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    Date::setTestNow('2026-11-03 08:00:00');
});

it('counts entities, staff and live payments on the System Administrator board', function (): void {
    $first = entityWithOwner();
    $second = entityWithOwner(static fn ($factory) => $factory->incompleteProfile());
    entityWithOwner(static fn ($factory) => $factory->pendingVerification());
    entityWithOwner(static fn ($factory) => $factory->deactivated());
    entityWithOwner()->delete();
    storedPaidPayment($first);
    storedPaidPayment($second);
    storedPaidPayment($first, IntegrationMode::Test);
    Payment::factory()->forEntity($second)->create();

    $response = boardOf(systemAdmin(), 'system_admin');
    $cards = boardCards($response);

    expect($cards->all())->toMatchArray([
        'entities_total' => 4,
        'entities_active' => 2,
        'entities_pending_verification' => 1,
        'staff_users_active' => 1,
        'payments_paid' => 2,
        'payments_open' => 1,
    ])
        ->and($cards->get('payments_collected'))->toBe('2300.00')
        ->and($cards->get('refunds_total'))->toBe('0.00')
        ->and(collect(boardWidget($response, 'entities_by_sector'))->sum('count'))->toBe(4)
        ->and(collect(boardWidget($response, 'entities_by_governance'))->pluck('count', 'key')->all())->toBe([
            'below_50' => 0, 'from_50_to_69' => 0, 'from_70_to_84' => 3, 'from_85_to_100' => 0, 'not_reported' => 1,
        ])
        ->and(collect(boardWidget($response, 'entities_by_region'))->firstWhere('region_id', $first->region_id)['count'] ?? null)->toBe(1)
        ->and(collect($response->json('data.cards'))->firstWhere('key', 'payments_collected')['format'] ?? null)->toBe('money');
});

it('builds the request boards from scoped, non-frozen requests', function (): void {
    $version = publishedAccreditedCriteria();
    $first = assessorUser();
    $second = assessorUser();
    $completed = completedAccreditedRequest($version, $first);
    $inReview = reviewedAccreditedRequest($version, $second);
    $waiting = submittedAccreditedRequest($version);

    $follower = boardCards(boardOf(requestFollower(), 'request_follower'));
    expect($follower->all())->toMatchArray([
        'awaiting_assignment' => 1, 'in_review' => 1, 'completed' => 1, 'overdue' => 0,
    ])->and($follower->get('completion_rate'))->toBe('33.33');

    $firstBoard = boardOf($first, 'assessor');
    $secondBoard = boardOf($second, 'assessor');
    expect(boardCards($firstBoard)->all())->toMatchArray(['to_start' => 0, 'in_progress' => 0, 'completed' => 1])
        ->and(boardWidget($firstBoard, 'due_soon'))->toBe([])
        ->and(collect(boardWidget($firstBoard, 'weakest_criteria'))->pluck('average_percent', 'code')->all())->toBe(['M2' => '75.00', 'M1' => '78.00'])
        ->and(boardCards($secondBoard)->all())->toMatchArray(['in_progress' => 1, 'completed' => 0])
        ->and(collect(boardWidget($secondBoard, 'due_soon'))->pluck('id')->all())->toBe([$inReview->id])
        ->and(boardWidget($secondBoard, 'weakest_criteria'))->toBe([]);

    $admin = boardOf(systemAdmin(), 'system_admin');
    expect(collect(boardWidget($admin, 'lowest_criteria'))->pluck('average_percent', 'code')->all())->toBe(['M2' => '75.00', 'M1' => '78.00'])
        ->and(boardWidget($admin, 'activity'))->not->toBeEmpty();

    Entity::query()->whereKey($waiting->entity_id)->firstOrFail()->delete();

    expect(boardCards(boardOf(requestFollower(), 'request_follower'))->get('awaiting_assignment'))->toBe(0)
        ->and($completed->refresh()->status->value)->toBe('completed');
});

it('shows the entity its own result, requests, payments card and unread notifications only', function (): void {
    $version = publishedAccreditedCriteria();
    $completed = completedAccreditedRequest($version, assessorUser());
    $owner = ownerOf($completed->entity);
    inAppNotification($owner);
    $stranger = entityWithOwner();
    Payment::factory()->forEntity($stranger)->create();

    $response = boardOf($owner);

    expect($response->json('data.board'))->toBe('entity')
        ->and(boardCards($response)->all())->toMatchArray([
            'latest_result_percent' => '77.00', 'open_requests' => 0, 'unread_notifications' => 1,
        ])
        ->and(boardWidget($response, 'latest_result'))->toMatchArray(['assessment_id' => $completed->id, 'path' => 'accredited', 'percent' => '77.00'])
        ->and(collect(boardWidget($response, 'criteria_progress'))->pluck('percent', 'code')->all())->toBe(['M1' => '78.00', 'M2' => '75.00'])
        ->and(collect(boardWidget($response, 'requests'))->pluck('id')->all())->toBe([$completed->id])
        ->and(boardWidget($response, 'payments_card'))->toMatchArray([
            'pending_payment' => null,
            'receipts_count' => 1,
            'link' => '/payments',
        ])
        ->and(boardWidget($response, 'payments_card')['last_payment']['status'] ?? null)->toBe('paid');

    $strangerBoard = boardOf(ownerOf($stranger));
    expect(boardWidget($strangerBoard, 'latest_result'))->toBeNull()
        ->and(boardWidget($strangerBoard, 'requests'))->toBe([])
        ->and(boardWidget($strangerBoard, 'payments_card')['pending_payment']['status'] ?? null)->toBe('created')
        ->and(boardWidget($strangerBoard, 'payments_card')['receipts_count'] ?? null)->toBe(0);
});

it('reports live revenue, receipts and daily payments on the finance board', function (): void {
    storedPaidPayment();
    storedPaidPayment();
    storedPaidPayment(null, IntegrationMode::Test);

    $response = boardOf(staffUser(RoleKey::FinanceOfficer));
    $cards = boardCards($response);

    expect($cards->all())->toMatchArray([
        'revenue' => '2300.00', 'vat_collected' => '300.00', 'payments_paid' => 2, 'receipts_issued' => 2, 'refunds_count' => 0,
    ])
        ->and(boardWidget($response, 'daily_payments'))->toBe([['date' => '2026-11-03', 'count' => 2, 'total' => '2300.00']])
        ->and(boardWidget($response, 'by_service'))->toBe([['purpose' => 'accredited', 'count' => 2, 'total' => '2300.00']]);
});

it('counts pages and indicators on the content board', function (): void {
    Kpi::factory()->create(['is_published' => true, 'published_at' => Date::now(), 'show_on_homepage' => true]);
    Kpi::factory()->create();

    $response = boardOf(contentOfficer());

    expect(boardCards($response)->all())->toMatchArray([
        'pages_total' => count(CmsPageKey::cases()), 'pages_published' => 0, 'kpis_total' => 2, 'kpis_published' => 1, 'kpis_featured' => 1,
    ])->and(collect(boardWidget($response, 'latest_edits'))->pluck('type')->unique()->values()->all())->toBe(['kpi']);
});

it('describes the published criteria and the draft problems on the criteria board', function (): void {
    publishedAccreditedCriteria();
    draftVersion()->forceFill(['validation_errors' => [['code' => 'weights_mismatch'], ['code' => 'no_points']]])->save();

    $response = boardOf(criteriaOfficer());

    expect(boardCards($response)->all())->toMatchArray([
        'main_criteria' => 2, 'sub_criteria' => 3, 'guiding_points' => 2, 'draft_problems' => 2,
    ])
        ->and(boardWidget($response, 'published_version')['version_number'] ?? null)->toBeInt()
        ->and(boardWidget($response, 'draft_version')['problems'] ?? null)->toBe(2);
});
