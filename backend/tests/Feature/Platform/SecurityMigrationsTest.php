<?php

declare(strict_types=1);

use App\Enums\Identity\AuthEventType;
use App\Models\Identity\AuthenticationEvent;
use App\Models\Identity\OtpCode;
use App\Models\Identity\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('rolls the Task-51 migrations back and forward again', function (string $file, string $table, array $columns): void {
    $migration = require database_path('migrations/'.$file.'.php');

    $migration->down();
    expect(Schema::hasColumns($table, $columns))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumns($table, $columns))->toBeTrue();
})->with([
    'identifier hash' => ['2026_11_04_160100_add_identifier_hash_to_authentication_events_table', 'authentication_events', ['identifier_type', 'identifier_hash']],
    'phone index' => ['2026_11_04_160200_add_phone_index_to_users_table', 'users', ['phone_index']],
    'unified number' => ['2026_11_04_160300_add_unified_number_to_entities_table', 'entities', ['unified_number']],
]);

function securityMigration(string $name): object
{
    return require database_path('migrations/'.$name.'.php');
}

/**
 * @param  array<string, mixed>  $overrides
 */
function insertOtpCode(User $user, string $purpose, array $overrides = []): void
{
    DB::table('otp_codes')->insert([
        'challenge_id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'channel' => 'email',
        'purpose' => $purpose,
        'code_hash' => str_repeat('a', 64),
        'destination_masked' => 'i***@example.sa',
        'expires_at' => now()->addMinutes(5),
        'last_sent_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
        ...$overrides,
    ]);
}

it('replaces the second-factor purpose with the sign-in code purpose and rolls back cleanly', function (): void {
    $owner = ownerOf(entityWithOwner());
    $migration = securityMigration('2026_11_05_140000_replace_login_second_factor_with_sign_in_code_in_otp_codes');
    insertOtpCode($owner, 'sign_in_code');

    $migration->down();

    expect(DB::table('otp_codes')->where('purpose', 'sign_in_code')->exists())->toBeFalse();
    insertOtpCode($owner, 'login_second_factor');
    expect(fn () => DB::transaction(fn () => insertOtpCode($owner, 'sign_in_code')))->toThrow(QueryException::class);

    $migration->up();

    expect(DB::table('otp_codes')->where('purpose', 'login_second_factor')->exists())->toBeFalse();
    expect(fn () => DB::transaction(fn () => insertOtpCode($owner, 'login_second_factor')))->toThrow(QueryException::class);
    requestSignInCode($owner->email)->assertOk()->assertJsonPath('data.step', 'otp_required');
    expect(OtpCode::query()->where('user_id', $owner->id)->where('purpose', 'sign_in_code')->exists())->toBeTrue();
});

it('removes the retired entity second-factor setting row', function (): void {
    DB::table('settings')->insert([
        'group' => 'auth',
        'key' => 'auth.entity_second_factor_enabled',
        'type' => 'boolean',
        'value' => 'true',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    securityMigration('2026_11_05_140100_remove_entity_second_factor_setting')->up();

    expect(DB::table('settings')->where('key', 'auth.entity_second_factor_enabled')->exists())->toBeFalse();
});

it('clears the authenticator of every entity account, records it and leaves staff untouched', function (): void {
    $enrolledOwner = ownerOf(entityWithOwner());
    $enrolledOwner->forceFill(totpAttributes())->save();
    $plainOwner = ownerOf(entityWithOwner());
    $staff = systemAdmin();
    Log::spy();

    securityMigration('2026_11_05_140200_clear_entity_authenticators')->up();

    $cleared = $enrolledOwner->refresh();
    expect($cleared->two_factor_secret)->toBeNull()
        ->and($cleared->two_factor_recovery_codes)->toBeNull()
        ->and($cleared->two_factor_confirmed_at)->toBeNull()
        ->and($staff->refresh()->hasConfirmedTotp())->toBeTrue()
        ->and(AuthenticationEvent::query()->where('type', AuthEventType::MfaDisabled)->pluck('user_id')->all())->toBe([$enrolledOwner->id])
        ->and(AuthenticationEvent::query()->where('user_id', $enrolledOwner->id)->sole()->meta)->toBe(['reason' => 'entity_second_factor_cancelled'])
        ->and(AuthenticationEvent::query()->where('user_id', $plainOwner->id)->exists())->toBeFalse();
    Log::shouldHaveReceived('info')->with('identity.entity_authenticators_cleared', ['count' => 1])->once();
    signInWith($cleared->email, defaultPassword())->assertOk()->assertJsonPath('data.step', 'authenticated');
});
