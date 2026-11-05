<?php

declare(strict_types=1);

use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\BlindIndexPurpose;
use App\Models\Identity\AuthenticationEvent;
use App\Models\Identity\User;
use App\Services\Identity\BlindIndex;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    setEntitySecondFactor(false);
});

function verifiedMobileOwner(string $phone = '+966551234567'): User
{
    $owner = ownerOf(entityWithOwner());
    $owner->forceFill(['phone' => $phone])->save();
    $owner->forceFill(['phone_verified_at' => Date::now()])->save();

    return $owner->refresh();
}

it('signs an entity in with its official e-mail', function (): void {
    $owner = ownerOf(entityWithOwner());

    signInWith($owner->email, defaultPassword())->assertOk()->assertJsonPath('data.step', 'authenticated')->assertJsonPath('data.user.id', $owner->id);
});

it('signs an entity in with a verified mobile number in every accepted format', function (string $typed): void {
    $owner = verifiedMobileOwner();

    signInWith($typed, defaultPassword())->assertOk()->assertJsonPath('data.user.id', $owner->id);
})->with(['0551234567', '+966551234567', '966551234567', '00966551234567', '551234567', '055 123 4567', '055-123-4567']);

it('signs the owner account in with the unified national number of its entity', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => '7012345678']));

    signInWith('7012345678', defaultPassword())->assertOk()->assertJsonPath('data.user.id', ownerOf($entity)->id);
});

it('refuses a mobile number that was never verified with the generic error', function (): void {
    $owner = ownerOf(entityWithOwner());
    $owner->forceFill(['phone' => '+966551234567'])->save();

    $answer = signInWith('0551234567', defaultPassword())->assertUnprocessable()->json();

    expect($answer['code'])->toBe('credentials.invalid')
        ->and($answer)->toBe(signInWith('no-account@moashir.example.sa', defaultPassword())->json());
});

it('forgets the verification when the mobile number changes', function (): void {
    $owner = verifiedMobileOwner();

    $owner->forceFill(['phone' => '+966559876543'])->save();

    expect($owner->refresh()->phone_verified_at)->toBeNull()
        ->and($owner->phone_index)->toBe(app(BlindIndex::class)->of(BlindIndexPurpose::Phone, '+966559876543'));
    signInWith('0559876543', defaultPassword())->assertUnprocessable()->assertJsonPath('code', 'credentials.invalid');
});

it('gives the same generic error for a wrong password and an unknown account of every identifier type', function (string $unknown): void {
    $owner = verifiedMobileOwner();
    $wrongPassword = signInWith($owner->email, 'Wrong-Password-2026!')->assertUnprocessable()->json();

    expect(signInWith($unknown, 'Wrong-Password-2026!')->assertUnprocessable()->json())->toBe($wrongPassword);
})->with(['nobody@moashir.example.sa', '0550000001', '7999999999']);

it('stores a keyed hash instead of the identifier of an unknown account', function (string $unknown, string $type, string $normalized): void {
    signInWith($unknown, 'Wrong-Password-2026!')->assertUnprocessable();

    $event = AuthenticationEvent::query()->where('type', AuthEventType::LoginFailed)->sole();
    $row = (array) DB::table('authentication_events')->where('id', $event->id)->first();

    expect($event->email_attempted)->toBeNull()
        ->and($event->user_id)->toBeNull()
        ->and($event->identifier_type)->toBe($type)
        ->and($event->identifier_hash)->toBe(app(BlindIndex::class)->of(BlindIndexPurpose::SignInIdentifier, $type.'|'.$normalized))
        ->and(json_encode($row))->not->toContain($normalized)
        ->and(json_encode($row))->not->toContain('5500');
})->with([
    'e-mail' => ['Nobody@Moashir.example.sa', 'email', 'nobody@moashir.example.sa'],
    'mobile' => ['0550000001', 'mobile', '+966550000001'],
    'unified number' => ['7999999999', 'unified_number', '7999999999'],
]);

it('locks the account whatever identifier the failures used', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => '7012345678']));
    $owner = ownerOf($entity);

    foreach (['7012345678', $owner->email, '7012345678', $owner->email] as $identifier) {
        signInWith($identifier, 'Wrong-Password-2026!')->assertJsonPath('code', 'credentials.invalid');
    }

    signInWith('7012345678', 'Wrong-Password-2026!')->assertStatus(423)->assertJsonPath('code', 'account_locked');
    signInWith($owner->email, defaultPassword())->assertStatus(423);
    expect(AuthenticationEvent::query()->where('user_id', $owner->id)->where('type', AuthEventType::LoginFailed)->count())->toBe(5);
});

it('throttles one identifier across addresses and one address across identifiers', function (): void {
    foreach (range(1, 20) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$attempt])->postJson('/api/v1/auth/login', ['identifier' => '7012345678', 'password' => 'Wrong-Password-2026!']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])->postJson('/api/v1/auth/login', ['identifier' => '7012345678', 'password' => 'Wrong-Password-2026!'])
        ->assertTooManyRequests();

    foreach (range(1, 30) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.1'])->postJson('/api/v1/auth/login', ['identifier' => '70100000'.str_pad((string) $attempt, 2, '0', STR_PAD_LEFT), 'password' => 'Wrong-Password-2026!']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.1'])->postJson('/api/v1/auth/login', ['identifier' => '7010000099', 'password' => 'Wrong-Password-2026!'])
        ->assertTooManyRequests();
});

it('tells an account without a password to use the reset flow by answering like a wrong password', function (): void {
    $owner = ownerOf(entityWithOwner());
    $owner->forceFill(['password' => null])->save();

    signInWith($owner->email, 'Any-Password-2026!')->assertUnprocessable()->assertJsonPath('code', 'credentials.invalid');
});

it('describes the simplified sign-in to the SPA', function (bool $enabled): void {
    setEntitySecondFactor($enabled);

    $this->getJson('/api/v1/auth/login-modes')
        ->assertOk()
        ->assertExactJson(['data' => [
            'identifiers' => ['email', 'mobile', 'unified_number'],
            'entity_second_factor_enabled' => $enabled,
            'otp_length' => 6,
            'otp_expiry_seconds' => 300,
            'resend_cooldown_seconds' => 60,
            'sso_providers' => [],
        ], 'meta' => [], 'errors' => null]);
})->with([true, false]);
