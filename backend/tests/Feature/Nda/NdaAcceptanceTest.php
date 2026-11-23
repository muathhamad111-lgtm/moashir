<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Identity\TermsAcceptance;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('records the acceptance with the roles held, the IP address and the browser, and audits it', function (): void {
    $version = publishedNda();
    $user = staffUser(RoleKey::Assessor, RoleKey::RequestFollower);

    signIn($user)->withHeader('User-Agent', 'Moashir-Test-Browser')
        ->postJson('/api/v1/me/nda/acceptances', ['terms_version_id' => $version->id])
        ->assertOk()
        ->assertJsonPath('data.accepted', true);

    $acceptance = TermsAcceptance::query()->where('user_id', $user->id)->where('terms_version_id', $version->id)->sole();
    expect($acceptance->accepted_roles)->toEqualCanonicalizing(['assessor', 'request_follower'])
        ->and($acceptance->user_agent)->toBe('Moashir-Test-Browser')
        ->and($acceptance->ip_address)->not->toBeNull();

    $audit = Audit::query()->where('event', AuditEvent::NdaAccepted->value)->sole();
    expect($audit->new_values)->toMatchArray(['terms_version_id' => $version->id, 'version' => '1.0'])
        ->and((string) json_encode($audit->new_values))->not->toContain($user->email);
});

it('answers a repeated acceptance the same way without a second row or audit entry', function (): void {
    $version = publishedNda();
    $user = staffUser(RoleKey::Assessor);

    signIn($user)->postJson('/api/v1/me/nda/acceptances', ['terms_version_id' => $version->id])->assertOk();
    signIn($user)->postJson('/api/v1/me/nda/acceptances', ['terms_version_id' => $version->id])->assertOk();

    expect(TermsAcceptance::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::NdaAccepted->value)->count())->toBe(1);
});

it('records the Super Admin acceptance under the super_admin role', function (): void {
    $version = publishedNda();
    $owner = superAdmin();

    signIn($owner)->postJson('/api/v1/me/nda/acceptances', ['terms_version_id' => $version->id])->assertOk();

    expect(TermsAcceptance::query()->where('user_id', $owner->id)->sole()->accepted_roles)->toBe(['super_admin']);
});

it('refuses a version that is not the current one', function (): void {
    $old = publishedNda('1.0');
    publishedNda('1.1');

    signIn(systemAdmin())->postJson('/api/v1/me/nda/acceptances', ['terms_version_id' => $old->id])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'nda.version_not_current');
});

it('validates the acceptance request', function (array $payload): void {
    publishedNda();

    signIn(systemAdmin())->postJson('/api/v1/me/nda/acceptances', $payload)->assertUnprocessable();
})->with([
    'missing version' => [[]],
    'not a number' => [['terms_version_id' => 'latest']],
    'unknown version' => [['terms_version_id' => 999_999]],
]);

it('keeps acceptances append-only and published versions immutable, change summary included', function (): void {
    $version = publishedNda();
    $acceptance = acceptNdaAs(staffUser(RoleKey::Assessor), $version);

    expect(fn () => DB::transaction(fn () => DB::table('terms_acceptances')->where('id', $acceptance->id)->update(['accepted_roles' => '[]'])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('terms_acceptances')->where('id', $acceptance->id)->delete()))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('terms_versions')->where('id', $version->id)->update(['change_summary' => '{"ar":"x","en":"y"}'])))
        ->toThrow(QueryException::class, 'append_only');
});

it('lets a user list and read again the versions they accepted', function (): void {
    $version = publishedNda();
    $other = publishedNda('1.1');
    $user = staffUser(RoleKey::Assessor);
    acceptNdaAs($user, $version);
    acceptNdaAs($user, $other);

    signIn($user)->getJson('/api/v1/me/nda/acceptances')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.version', '1.1')
        ->assertJsonPath('data.0.ip_masked', '127.0.0.•');
    signIn($user)->getJson("/api/v1/me/nda/versions/{$version->id}")->assertOk()->assertJsonPath('data.version', '1.0');
});

it('does not show a version the user never accepted', function (): void {
    $version = publishedNda();
    $accepted = publishedNda('1.1');
    $user = staffUser(RoleKey::Assessor);
    acceptNdaAs($user, $accepted);

    signIn($user)->getJson("/api/v1/me/nda/versions/{$version->id}")->assertForbidden();
    signIn($user)->getJson('/api/v1/me/nda/versions/'.legalDraft()->id)->assertNotFound();
});
