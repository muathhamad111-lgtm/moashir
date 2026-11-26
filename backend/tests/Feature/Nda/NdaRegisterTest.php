<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Identity\User;

/**
 * @return array{admin: User, accepted: User, pending: User}
 */
function ndaRegisterPeople(): array
{
    $version = publishedNda();
    $admin = systemAdmin();
    $accepted = staffUser(RoleKey::Assessor);
    $pending = staffUser(RoleKey::CriteriaOfficer);
    staffUser(RoleKey::FinanceOfficer);
    acceptNdaAs($admin, $version);
    acceptNdaAs($accepted, $version);

    return ['admin' => $admin, 'accepted' => $accepted, 'pending' => $pending];
}

it('counts the in-scope staff against the current version and masks the IP address', function (): void {
    ['admin' => $admin, 'accepted' => $accepted] = ndaRegisterPeople();

    $response = signIn($admin)->getJson('/api/v1/settings/nda/acceptances')
        ->assertOk()
        ->assertJsonPath('meta.summary', ['in_scope' => 3, 'accepted' => 2, 'pending' => 1])
        ->assertJsonPath('meta.version.version', '1.0')
        ->assertJsonPath('meta.pagination.total', 3);

    $row = collect($response->json('data'))->firstWhere('user.id', $accepted->id);
    expect($row['status'])->toBe('accepted')
        ->and($row['ip_masked'])->toBe('127.0.0.•')
        ->and($row['accepted_roles'])->toBe(['assessor']);
});

it('leaves the staff e-mail out of the register rows', function (): void {
    ['admin' => $admin, 'accepted' => $accepted] = ndaRegisterPeople();

    $response = signIn($admin)->getJson('/api/v1/settings/nda/acceptances')->assertOk();

    expect(collect($response->json('data'))->firstWhere('user.id', $accepted->id)['user'])->toHaveKeys(['id', 'name', 'roles'])->not->toHaveKey('email')
        ->and((string) $response->getContent())->not->toContain($accepted->email);
});

it('filters the register by pending and accepted', function (string $filter, string $person): void {
    $people = ndaRegisterPeople();

    $ids = collect(signIn($people['admin'])->getJson("/api/v1/settings/nda/acceptances?filter={$filter}")->assertOk()->json('data'))->pluck('user.id');

    expect($ids)->toContain($people[$person]->id);
})->with([['pending', 'pending'], ['accepted', 'accepted']]);

it('exports the register as CSV with a dated file name, neutralised formulas and an audit entry', function (): void {
    ['admin' => $admin, 'pending' => $pending] = ndaRegisterPeople();
    $pending->forceFill(['name' => '=HYPERLINK("http://evil.example","x")'])->save();

    $response = signIn($admin)->getJson('/api/v1/settings/nda/acceptances/export');

    $response->assertOk();
    $csv = $response->streamedContent();
    expect($response->headers->get('Content-Disposition'))->toContain('nda-acceptances-'.now()->format('Y-m-d').'.csv')
        ->and($response->headers->get('Content-Disposition'))->not->toContain('@')
        ->and($csv)->toContain("'=HYPERLINK")
        ->and($csv)->not->toContain(',=HYPERLINK');

    $audit = Audit::query()->where('event', AuditEvent::NdaAcceptancesExported->value)->sole();
    expect($audit->new_values)->toMatchArray(['row_count' => 3, 'filter' => 'all']);
});

it('shows the agreement status in the users list and filters the pending ones', function (): void {
    ['admin' => $admin, 'pending' => $pending, 'accepted' => $accepted] = ndaRegisterPeople();

    $rows = collect(signIn($admin)->getJson('/api/v1/users')->assertOk()->assertJsonPath('meta.nda_scope_roles.0', 'system_admin')->json('data'));
    expect($rows->firstWhere('id', $pending->id)['nda_status'])->toBe('pending')
        ->and($rows->firstWhere('id', $accepted->id)['nda_status'])->toBe('accepted');

    $pendingOnly = collect(signIn($admin)->getJson('/api/v1/users?filter[nda]=pending')->assertOk()->json('data'))->pluck('id');
    expect($pendingOnly->all())->toBe([$pending->id]);
});

it('lists a staff member agreement history on the staff page', function (): void {
    ['admin' => $admin, 'accepted' => $accepted] = ndaRegisterPeople();

    signIn($admin)->getJson("/api/v1/users/{$accepted->id}/nda-acceptances")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.version', '1.0')
        ->assertJsonPath('data.0.title.en', 'Non-disclosure agreement')
        ->assertJsonPath('data.0.effective_from', now()->toDateString());
    switchTo(staffUser(RoleKey::FinanceOfficer))->getJson("/api/v1/users/{$accepted->id}/nda-acceptances")->assertForbidden();
});
