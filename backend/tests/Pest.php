<?php

declare(strict_types=1);

use App\Enums\Identity\AssessorQualification;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\BreachedPassword;
use App\Models\Identity\User;
use App\Models\Identity\UserAssessorQualification;
use App\Services\Identity\SessionKeys;
use App\Services\Identity\SuperAdminFlagWriter;
use App\Support\ValueObjects\PasswordDigest;
use Database\Factories\Identity\UserFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\ParallelTesting;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

require_once __DIR__.'/Support/criteria-helpers.php';
require_once __DIR__.'/Support/entity-helpers.php';
require_once __DIR__.'/Support/assessment-helpers.php';
require_once __DIR__.'/Support/integration-helpers.php';
require_once __DIR__.'/Support/finance-helpers.php';
require_once __DIR__.'/Support/bank-transfer-helpers.php';
require_once __DIR__.'/Support/file-helpers.php';
require_once __DIR__.'/Support/accredited-helpers.php';
require_once __DIR__.'/Support/advanced-helpers.php';
require_once __DIR__.'/Support/committee-helpers.php';
require_once __DIR__.'/Support/content-helpers.php';
require_once __DIR__.'/Support/notification-helpers.php';
require_once __DIR__.'/Support/dashboard-helpers.php';
require_once __DIR__.'/Support/certificate-helpers.php';
require_once __DIR__.'/Support/contact-helpers.php';
require_once __DIR__.'/Support/entity-logo-helpers.php';
require_once __DIR__.'/Support/sign-in-helpers.php';

pest()->extend(TestCase::class)->in('Feature', 'Unit');

const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

const TEST_DATABASE = 'moashir_testing';

function suiteTestDatabase(): string
{
    $token = ParallelTesting::token();

    if (empty($_SERVER['LARAVEL_PARALLEL_TESTING']) || ! is_scalar($token) || (string) $token === '') {
        return TEST_DATABASE;
    }

    return TEST_DATABASE.'_test_'.$token;
}

function staffUser(RoleKey ...$roles): User
{
    $user = unqualifiedStaffUser(...$roles);

    if (in_array(RoleKey::Assessor, $roles, true)) {
        qualifyAssessor($user, ...AssessorQualification::cases());
    }

    return $user->refresh();
}

function unqualifiedStaffUser(RoleKey ...$roles): User
{
    $user = User::factory()->withConfirmedTotp(TOTP_SECRET)->create(['mfa_required' => true]);
    $user->assignRole(array_map(static fn (RoleKey $role): string => $role->value, $roles));

    return $user->refresh();
}

function qualifyAssessor(User $user, AssessorQualification ...$qualifications): User
{
    foreach ($qualifications as $qualification) {
        UserAssessorQualification::query()->firstOrCreate(['user_id' => $user->id, 'qualification' => $qualification]);
    }

    return $user->refresh();
}

function systemAdmin(): User
{
    return staffUser(RoleKey::SystemAdmin);
}

function entityUser(): User
{
    return User::factory()->withRole(RoleKey::Entity)->create()->refresh();
}

function superAdmin(): User
{
    $user = User::factory()->create();
    app(SuperAdminFlagWriter::class)->grant($user);
    $user->forceFill(totpAttributes())->save();

    return $user->refresh();
}

/**
 * @return array<string, mixed>
 */
function totpAttributes(): array
{
    return [
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt(TOTP_SECRET),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code-one', 'recovery-code-two'], JSON_THROW_ON_ERROR)),
        'two_factor_confirmed_at' => Date::now(),
    ];
}

function currentTotp(): string
{
    return (new Google2FA)->getCurrentOtp(TOTP_SECRET);
}

function signIn(User $user): TestCase
{
    $now = Date::now()->getTimestamp();

    return test()
        ->actingAs($user, 'web')
        ->withSession([SessionKeys::SIGNED_IN_AT => $now, SessionKeys::LAST_SEEN_AT => $now]);
}

function defaultPassword(): string
{
    return UserFactory::PASSWORD;
}

function markPasswordBreached(string $password): void
{
    BreachedPassword::query()->create(['digest' => PasswordDigest::ofPassword($password)->value]);
}

function temporaryListFile(string $contents): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'moashir-list-');
    file_put_contents($path, $contents);
    test()->beforeApplicationDestroyed(static fn (): bool => unlink($path));

    return $path;
}
