<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Models\Identity\Role;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    Date::setTestNow('2026-11-03 08:00:00');
});

it('lands every single-role user on the board of their role', function (RoleKey $role): void {
    $user = $role === RoleKey::Entity ? entityUser() : staffUser($role);
    $expected = $role === RoleKey::SystemAdmin ? ['system_admin', 'executive'] : [$role->value];

    signIn($user)->getJson(DASHBOARD)
        ->assertOk()
        ->assertJsonPath('data.board', $role->value)
        ->assertJsonPath('data.landing_board', $role->value)
        ->assertJsonPath('data.available_boards', $expected)
        ->assertJsonStructure(['data' => ['cards' => [['key', 'value', 'format', 'link']], 'widgets', 'action_center', 'generated_at']]);
})->with(RoleKey::cases());

it('lands the Super Admin on the System Administrator board with the executive board available', function (): void {
    signIn(superAdmin())->getJson(DASHBOARD)
        ->assertOk()
        ->assertJsonPath('data.board', 'system_admin')
        ->assertJsonPath('data.available_boards', ['system_admin', 'executive']);
});

it('gives a multi-role user one workspace with the landing board by precedence and every held board on request', function (): void {
    $user = staffUser(RoleKey::FinanceOfficer, RoleKey::RequestFollower, RoleKey::Assessor);

    signIn($user)->getJson(DASHBOARD)
        ->assertOk()
        ->assertJsonPath('data.board', 'request_follower')
        ->assertJsonPath('data.available_boards', ['request_follower', 'assessor', 'finance_officer']);

    $this->getJson(DASHBOARD.'?board=assessor')->assertOk()->assertJsonPath('data.board', 'assessor')->assertJsonPath('data.landing_board', 'request_follower');
    $this->getJson(DASHBOARD.'?board=finance_officer')->assertOk()->assertJsonPath('data.board', 'finance_officer');
});

it('lands the award secretary before the filtering officer and the follower', function (): void {
    signIn(staffUser(RoleKey::RequestFollower, RoleKey::FilteringOfficer, RoleKey::AwardSecretary))->getJson(DASHBOARD)
        ->assertOk()
        ->assertJsonPath('data.available_boards', ['award_secretary', 'filtering_officer', 'request_follower']);
});

it('refuses a board the user does not hold and an unknown board', function (): void {
    signIn(staffUser(RoleKey::Assessor));

    $this->getJson(DASHBOARD.'?board=system_admin')->assertForbidden()->assertJsonPath('code', 'board.not_held');
    $this->getJson(DASHBOARD.'?board=executive')->assertForbidden()->assertJsonPath('code', 'board.not_held');
    $this->getJson(DASHBOARD.'?board=committee_assessor')->assertForbidden()->assertJsonPath('code', 'board.not_held');
    $this->getJson(DASHBOARD.'?board=customer_service')->assertUnprocessable()->assertJsonValidationErrors(['board']);
});

it('opens the read-only executive board to holders of the independent permission only', function (): void {
    $finance = staffUser(RoleKey::FinanceOfficer);
    signIn($finance)->getJson(DASHBOARD.'?board=executive')->assertForbidden();

    Role::findByName(RoleKey::FinanceOfficer->value, 'web')->givePermissionTo('dashboard.executive.view');

    $response = switchTo($finance->refresh())->getJson(DASHBOARD.'?board=executive')
        ->assertOk()
        ->assertJsonPath('data.board', 'executive')
        ->assertJsonPath('data.landing_board', 'finance_officer')
        ->assertJsonPath('data.available_boards', ['finance_officer', 'executive']);

    expect(collect($response->json('data.cards'))->pluck('link')->filter()->all())->toBe([])
        ->and(collect($response->json('data.widgets'))->pluck('key')->all())->toBe(['entities_by_region', 'entities_by_sector', 'entities_by_governance', 'entities_by_level', 'lowest_criteria']);
});

it('keeps a seated assessor on the assessor board while the committee window is not open', function (): void {
    [$cycle, , $secretary] = committeeCycle();
    [$seated] = appointCommittee($cycle, $secretary, 1);

    switchTo($seated)->getJson(DASHBOARD)
        ->assertOk()
        ->assertJsonPath('data.board', 'assessor')
        ->assertJsonPath('data.available_boards', ['assessor', 'committee_assessor']);
    $this->getJson(DASHBOARD.'?board=committee_assessor')->assertOk()->assertJsonPath('data.board', 'committee_assessor');
});

it('lands a seated assessor on the committee board first while the committee window is open', function (): void {
    $scenario = committeeScenario(1, 1);

    switchTo($scenario['assessors'][0])->getJson(DASHBOARD)
        ->assertOk()
        ->assertJsonPath('data.board', 'committee_assessor')
        ->assertJsonPath('data.landing_board', 'committee_assessor')
        ->assertJsonPath('data.available_boards', ['assessor', 'committee_assessor']);
});

it('requires a session and the dashboard.view permission', function (): void {
    $this->getJson(DASHBOARD)->assertUnauthorized();
    $this->getJson('/api/v1/dashboard/action-center')->assertUnauthorized();

    $user = staffUser(RoleKey::ContentOfficer);
    Role::findByName(RoleKey::ContentOfficer->value, 'web')->revokePermissionTo('dashboard.view');

    signIn($user->refresh())->getJson(DASHBOARD)->assertForbidden()->assertJsonPath('code', 'forbidden');
    $this->getJson('/api/v1/dashboard/action-center')->assertForbidden();
});
