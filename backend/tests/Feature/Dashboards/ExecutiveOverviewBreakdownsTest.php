<?php

declare(strict_types=1);

use App\Enums\Entities\DevelopmentSector;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Region;
use App\Models\Identity\Role;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-17 08:00:00');
});

it('returns every breakdown with its keys and shape, in the contract order', function (): void {
    $entity = profiledEntity();
    storedPaidPayment($entity);
    Assessment::factory()->forEntity($entity)->create();

    $breakdowns = overviewOf(systemAdmin(), 'period=month')->json('data.breakdowns');
    $region = Region::query()->findOrFail($entity->region_id);

    expect(array_keys($breakdowns))->toBe([
        'requests_by_status', 'payments_by_status', 'entities_by_type', 'entities_by_size', 'entities_by_region',
        'assessor_workload', 'kpi_targets', 'finance', 'award', 'inbox', 'users', 'storage',
    ])
        ->and(collect($breakdowns['requests_by_status'])->pluck('count', 'key')->all())
        ->toBe(['awaiting_payment' => 1, 'in_queue' => 0, 'under_review' => 0, 'needs_completion' => 0, 'completed' => 0, 'declined' => 0])
        ->and(collect($breakdowns['payments_by_status'])->pluck('count', 'key')->all())
        ->toBe(['paid' => 1, 'pending' => 0, 'under_review' => 0, 'failed' => 0, 'cancelled' => 0, 'refunded' => 0])
        ->and(collect($breakdowns['entities_by_type'])->pluck('key')->all())->toBe(['civil_association', 'civil_foundation', 'waqf'])
        ->and(collect($breakdowns['entities_by_size'])->pluck('key')->all())->toBe(['small', 'medium', 'large'])
        ->and($breakdowns['entities_by_region'])->toBe([['key' => $region->code, 'name' => $region->name->toArray(), 'count' => 1]])
        ->and($breakdowns['assessor_workload'])->toBe([])
        ->and($breakdowns['kpi_targets'])->toBe([])
        ->and($breakdowns['finance'])->toBe(['vat_collected' => '150.00', 'refunds_total' => '0.00', 'receipts_issued' => 1, 'transfers_awaiting_review' => 0, 'abandoned_carts' => 0])
        ->and($breakdowns['award'])->toBeNull()
        ->and($breakdowns['inbox'])->toBe(['received' => 0, 'awaiting_reply' => 0, 'replied' => 0, 'average_reply_hours' => null])
        ->and(array_keys($breakdowns['users']))->toBe(['staff_active', 'entity_accounts', 'mfa_share', 'locked', 'failed_logins'])
        ->and($breakdowns['users']['mfa_share'])->toEqual(100)
        ->and(array_keys($breakdowns['storage']))->toBe(['quota_bytes', 'used_bytes', 'added_in_period_bytes', 'warning_percent', 'critical_percent', 'measured_at'])
        ->and($breakdowns['storage'])->toMatchArray(['quota_bytes' => 53_687_091_200, 'warning_percent' => 80, 'critical_percent' => 95, 'measured_at' => null]);
});

it('shows the storage figure to the system administrator and the super admin only', function (): void {
    $finance = staffUser(RoleKey::FinanceOfficer);
    Role::findByName(RoleKey::FinanceOfficer->value, 'web')->givePermissionTo('dashboard.executive.view');

    expect(overviewOf(systemAdmin())->json('data.breakdowns'))->toHaveKey('storage')
        ->and(overviewOf(superAdmin())->json('data.breakdowns'))->toHaveKey('storage')
        ->and(overviewOf($finance->refresh())->json('data.breakdowns'))->not->toHaveKey('storage')->toHaveKey('users');

    Role::findByName(RoleKey::FinanceOfficer->value, 'web')->givePermissionTo('dashboard.storage.view');

    expect(overviewOf($finance->refresh())->json('data.breakdowns'))->toHaveKey('storage');
});

it('keeps the filtered overview to holders of the executive dashboard permission', function (): void {
    $this->getJson(DASHBOARD_OVERVIEW.'?region=riyadh')->assertUnauthorized();

    signIn(staffUser(RoleKey::FinanceOfficer))->getJson(DASHBOARD_OVERVIEW.'?path=accredited')->assertForbidden();
    switchTo(ownerOf(entityWithOwner()))->getJson(DASHBOARD_OVERVIEW.'?type=waqf')->assertForbidden();
    switchTo(staffUser(RoleKey::ContentOfficer))->getJson(DASHBOARD_OVERVIEW.'?region=unknown')->assertForbidden();
});

it('counts in the breakdowns only what falls inside the chosen period', function (): void {
    Date::setTestNow('2026-10-06 09:00:00');
    $october = profiledEntity();
    storedPaidPayment($october);
    Assessment::factory()->forEntity($october)->create();
    contactMessage();
    Date::setTestNow('2026-11-17 08:00:00');

    $november = overviewOf(systemAdmin(), 'period=month')->json('data.breakdowns');
    $previous = overviewOf(systemAdmin(), 'period=month&year=2026&month=10')->json('data.breakdowns');

    expect(collect($november['requests_by_status'])->sum('count'))->toBe(0)
        ->and(collect($november['payments_by_status'])->sum('count'))->toBe(0)
        ->and($november['finance'])->toMatchArray(['vat_collected' => '0.00', 'receipts_issued' => 0])
        ->and($november['inbox']['received'])->toBe(0)
        ->and(collect($previous['requests_by_status'])->sum('count'))->toBe(1)
        ->and(collect($previous['payments_by_status'])->sum('count'))->toBe(1)
        ->and($previous['finance'])->toMatchArray(['vat_collected' => '150.00', 'receipts_issued' => 1])
        ->and($previous['inbox']['received'])->toBe(1);
});

it('shows the snapshot as it stood at the period end', function (): void {
    $older = profiledEntity(['development_sector' => DevelopmentSector::Health]);
    $older->forceFill(['created_at' => '2026-10-05 09:00:00'])->saveQuietly();
    profiledEntity(['development_sector' => DevelopmentSector::Social]);

    $october = overviewOf(systemAdmin(), 'period=month&year=2026&month=10');
    $november = overviewOf(systemAdmin(), 'period=month');

    expect(overviewWidget($october, 'entities_by_sector'))->toBe([['key' => 'health', 'count' => 1]])
        ->and(collect(overviewWidget($november, 'entities_by_sector'))->pluck('count', 'key')->all())->toBe(['health' => 1, 'social' => 1])
        ->and(collect(overviewWidget($october, 'entities_by_level'))->sum('count'))->toBe(1)
        ->and(collect(overviewWidget($october, 'entities_by_governance'))->sum('count'))->toBe(2);
});

it('binds the weakest criteria to the accredited assessments completed in the period', function (): void {
    completedAccreditedRequest(publishedAccreditedCriteria(), assessorUser());

    expect(overviewWidget(overviewOf(systemAdmin(), 'period=month'), 'lowest_criteria'))->not->toBe([])
        ->and(overviewWidget(overviewOf(systemAdmin(), 'period=month&year=2026&month=10'), 'lowest_criteria'))->toBe([]);
});
