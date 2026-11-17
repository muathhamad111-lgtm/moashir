<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Identity\Role;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    Date::setTestNow('2026-11-17 08:00:00');
});

function registeredAt(string $utcMoment): Entity
{
    $entity = entityWithOwner();
    $entity->forceFill(['created_at' => $utcMoment])->saveQuietly();

    return $entity;
}

function paidAt(Entity $entity, string $utcMoment): Payment
{
    $payment = storedPaidPayment($entity);
    $payment->forceFill(['paid_at' => $utcMoment])->saveQuietly();

    return $payment;
}

it('keeps the overview to holders of the executive dashboard permission', function (): void {
    $this->getJson(DASHBOARD_OVERVIEW)->assertUnauthorized();

    $finance = staffUser(RoleKey::FinanceOfficer);
    signIn($finance)->getJson(DASHBOARD_OVERVIEW)->assertForbidden();
    switchTo(ownerOf(entityWithOwner()))->getJson(DASHBOARD_OVERVIEW)->assertForbidden();

    Role::findByName(RoleKey::FinanceOfficer->value, 'web')->givePermissionTo('dashboard.executive.view');

    overviewOf($finance->refresh())->assertJsonPath('data.period.preset', 'month');
    overviewOf(superAdmin())->assertJsonPath('data.period.preset', 'month');
});

it('runs a preset from its first day to today, compared with the same length just before', function (string $preset, string $from, array $previous, string $bucket, int $buckets): void {
    $response = overviewOf(systemAdmin(), 'period='.$preset);

    expect($response->json('data.period'))->toBe(['from' => $from, 'to' => '2026-11-17', 'preset' => $preset])
        ->and($response->json('data.previous_period'))->toBe($previous)
        ->and($response->json('data.trends.bucket'))->toBe($bucket)
        ->and($response->json('data.trends.buckets'))->toHaveCount($buckets);
})->with([
    'month' => ['month', '2026-11-01', ['from' => '2026-10-15', 'to' => '2026-10-31'], 'day', 17],
    'quarter' => ['quarter', '2026-10-01', ['from' => '2026-08-14', 'to' => '2026-09-30'], 'week', 8],
    'year' => ['year', '2026-01-01', ['from' => '2025-02-14', 'to' => '2025-12-31'], 'month', 11],
]);

it('counts registrations and collections inside the period and the previous one, on Riyadh days', function (): void {
    $november = registeredAt('2026-10-31 22:00:00');
    registeredAt('2026-11-10 09:00:00');
    $october = registeredAt('2026-10-20 09:00:00');
    registeredAt('2026-10-14 20:00:00');
    paidAt($november, '2026-11-05 10:00:00');
    paidAt($november, '2026-11-06 22:00:00');
    paidAt($october, '2026-10-16 10:00:00');

    $response = overviewOf(systemAdmin(), 'period=month');
    $cards = overviewCards($response);

    expect($cards->get('new_entities'))->toBe(['value' => 2, 'previous' => 1])
        ->and($cards->get('payments_collected'))->toBe(['value' => '2300.00', 'previous' => '1150.00'])
        ->and($cards->get('entities_active')['previous'])->toBeNull()
        ->and(collect($response->json('data.cards'))->pluck('format', 'key')->all())->toMatchArray([
            'payments_collected' => 'money', 'accredited_average_percent' => 'percent', 'new_entities' => 'count',
        ]);

    $trends = $response->json('data.trends');
    $day = array_flip($trends['buckets']);

    expect($trends['series']['entities'][$day['2026-11-01']])->toBe(1)
        ->and($trends['series']['entities'][$day['2026-11-10']])->toBe(1)
        ->and(array_sum($trends['series']['entities']))->toBe(2)
        ->and($trends['series']['collected'][$day['2026-11-05']])->toBe('1150.00')
        ->and($trends['series']['collected'][$day['2026-11-06']])->toBe('0.00')
        ->and($trends['series']['collected'][$day['2026-11-07']])->toBe('1150.00');
});

it('counts submitted and completed requests by path in the period', function (): void {
    $version = publishedAccreditedCriteria();
    completedAccreditedRequest($version, assessorUser());
    submittedAccreditedRequest($version);

    $response = overviewOf(systemAdmin(), 'period=month');
    $paths = collect($response->json('data.paths'))->keyBy('path');

    expect($paths->get('accredited'))->toBe(['path' => 'accredited', 'submitted' => 2, 'completed' => 1])
        ->and($paths->get('advanced')['completed'])->toBeNull()
        ->and($paths->get('self')['submitted'])->toBeNull()
        ->and(overviewCards($response)->get('requests_submitted')['value'])->toBe(2)
        ->and(overviewCards($response)->get('accredited_average_percent')['value'])->not->toBeNull();
});

it('accepts a custom period and refuses a malformed one', function (): void {
    signIn(systemAdmin());

    $this->getJson(DASHBOARD_OVERVIEW.'?period=custom&from=2026-09-01&to=2026-09-30')->assertOk()
        ->assertJsonPath('data.period', ['from' => '2026-09-01', 'to' => '2026-09-30', 'preset' => 'custom'])
        ->assertJsonPath('data.previous_period', ['from' => '2026-08-02', 'to' => '2026-08-31']);

    $this->getJson(DASHBOARD_OVERVIEW.'?period=custom')->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
    $this->getJson(DASHBOARD_OVERVIEW.'?period=custom&from=2026-09-30&to=2026-09-01')->assertUnprocessable()->assertJsonValidationErrors(['to']);
    $this->getJson(DASHBOARD_OVERVIEW.'?period=custom&from=2026-11-01&to=2026-11-18')->assertUnprocessable()->assertJsonValidationErrors(['to']);
    $this->getJson(DASHBOARD_OVERVIEW.'?period=custom&from=2020-01-01&to=2026-11-17')->assertUnprocessable()->assertJsonValidationErrors(['to']);
    $this->getJson(DASHBOARD_OVERVIEW.'?period=custom&from=01-09-2026&to=2026-09-30')->assertUnprocessable()->assertJsonValidationErrors(['from']);
    $this->getJson(DASHBOARD_OVERVIEW.'?period=decade')->assertUnprocessable()->assertJsonValidationErrors(['period']);
});

it('builds the overview with a constant number of queries', function (): void {
    $admin = systemAdmin();
    registeredAt('2026-11-02 09:00:00');
    $before = overviewQueryCount($admin);

    for ($index = 0; $index < 4; $index++) {
        paidAt(registeredAt('2026-11-0'.($index + 3).' 09:00:00'), '2026-11-0'.($index + 3).' 10:00:00');
    }

    expect(overviewQueryCount($admin))->toBe($before);
});
