<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Entities\EntityStatus;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Platform\DemoRecordKind;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Identity\User;
use App\Models\Platform\DemoRecord;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

function demoAccountsDirectory(): string
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'moashir-demo-'.bin2hex(random_bytes(6));
    mkdir($directory, 0700);

    test()->beforeApplicationDestroyed(static function () use ($directory): void {
        array_map(unlink(...), glob($directory.DIRECTORY_SEPARATOR.'*') ?: []);
        rmdir($directory);
    });

    return $directory;
}

function enableDemoData(): void
{
    config()->set('moashir.demo.enabled', true);
}

function installDemoPrerequisites(): void
{
    installedSeedCriteria();
    test()->artisan('moashir:regions:install-seed')->assertSuccessful();
}

/**
 * @return array{exit: int, output: string}
 */
function runDemoCommand(string $command, string $accountsFile): array
{
    $exit = Artisan::call($command, ['--accounts-file' => $accountsFile]);

    return ['exit' => $exit, 'output' => Artisan::output()];
}

/**
 * @return array<string, string>
 */
function demoPasswordsIn(string $accountsFile): array
{
    preg_match_all('/^email:\s+(\S+)\R^password:\s+(\S+)$/m', (string) file_get_contents($accountsFile), $matches);

    return array_combine($matches[1], $matches[2]);
}

function expectRefusalWasLogged(string $reason): void
{
    Log::shouldHaveReceived('critical')
        ->withArgs(static fn (string $message, array $context): bool => $message === 'platform.demo_data_refused' && $context['reason'] === $reason)
        ->once();
}

it('refuses to install demo data unless APP_DEMO_DATA is explicitly true', function (): void {
    Log::spy();
    installDemoPrerequisites();
    $accountsFile = demoAccountsDirectory().'/accounts.txt';

    $result = runDemoCommand('moashir:demo:install', $accountsFile);

    expect($result['exit'])->toBe(1)
        ->and($result['output'])->toContain('APP_DEMO_DATA is not set to true')
        ->and(file_exists($accountsFile))->toBeFalse()
        ->and(DemoRecord::query()->count())->toBe(0)
        ->and(Entity::query()->count())->toBe(0);
    expectRefusalWasLogged('flag_not_set');
});

it('refuses to install demo data when an entity was not created by the demo command', function (): void {
    Log::spy();
    enableDemoData();
    installDemoPrerequisites();
    Entity::factory()->withOwner()->create();
    $usersBefore = User::query()->count();
    $accountsFile = demoAccountsDirectory().'/accounts.txt';

    $result = runDemoCommand('moashir:demo:install', $accountsFile);

    expect($result['exit'])->toBe(1)
        ->and($result['output'])->toContain('1 entity record(s) were not created by the demo command')
        ->and(file_exists($accountsFile))->toBeFalse()
        ->and(User::query()->count())->toBe($usersBefore)
        ->and(DemoRecord::query()->count())->toBe(0);
    expectRefusalWasLogged('real_data_present');
});

it('installs fictional demo data, writes the passwords only to a private accounts file and never to the output', function (): void {
    enableDemoData();
    installDemoPrerequisites();
    $accountsFile = demoAccountsDirectory().'/accounts.txt';

    $result = runDemoCommand('moashir:demo:install', $accountsFile);
    $passwords = demoPasswordsIn($accountsFile);

    expect($result['exit'])->toBe(0)
        ->and($result['output'])->toContain('Demo data installed: 8 staff accounts, 3 entities with their owner accounts, 2 TEST-mode payments')
        ->and(fileperms($accountsFile) & 0777)->toBe(0600)
        ->and($passwords)->toHaveCount(11);

    foreach ($passwords as $email => $password) {
        expect($result['output'])->not->toContain($password)
            ->and(strlen($password))->toBeGreaterThanOrEqual(12)
            ->and(Hash::check($password, (string) User::query()->where('email', $email)->value('password')))->toBeTrue();
    }

    foreach (RoleKey::cases() as $role) {
        $holders = User::role($role->value)->get();
        expect($holders)->not->toBeEmpty();

        if ($role->isStaff()) {
            expect($holders->every(static fn (User $user): bool => $user->mfa_required))->toBeTrue();
        }
    }

    expect(User::query()->where('is_super_admin', true)->exists())->toBeFalse()
        ->and(Entity::query()->where('status', EntityStatus::Active)->whereNotNull('verified_at')->count())->toBe(3)
        ->and(DemoRecord::query()->where('kind', DemoRecordKind::Entity)->count())->toBe(3)
        ->and(DemoRecord::query()->where('kind', DemoRecordKind::User)->count())->toBe(11)
        ->and(Payment::query()->where('mode', '<>', IntegrationMode::Test->value)->exists())->toBeFalse()
        ->and(Payment::query()->where('status', PaymentStatus::Paid)->count())->toBe(1)
        ->and(Payment::query()->where('status', PaymentStatus::Created)->count())->toBe(1)
        ->and(PaymentReceipt::query()->sole()->only(['number', 'is_test']))->toBe(['number' => null, 'is_test' => true]);

    $paid = Payment::query()->where('status', PaymentStatus::Paid)->sole();
    $cycle = AwardCycle::query()->sole();

    expect(Assessment::query()->where('entity_id', $paid->entity_id)->where('path', AssessmentPath::Accredited)->exists())->toBeTrue()
        ->and($cycle->status)->toBe(AwardCycleStatus::Draft)
        ->and($cycle->members()->where('role', CycleMemberRole::AwardSecretary)->exists())->toBeTrue();
});

it('refuses a second installation and leaves the first one untouched', function (): void {
    Log::spy();
    enableDemoData();
    installDemoPrerequisites();
    $directory = demoAccountsDirectory();
    runDemoCommand('moashir:demo:install', $directory.'/first.txt');

    $result = runDemoCommand('moashir:demo:install', $directory.'/second.txt');

    expect($result['exit'])->toBe(1)
        ->and($result['output'])->toContain('already installed')
        ->and(file_exists($directory.'/second.txt'))->toBeFalse()
        ->and(Entity::query()->count())->toBe(3);
    expectRefusalWasLogged('already_installed');
});

it('refuses an accounts file that exists, is relative or lies inside the application', function (string $kind): void {
    enableDemoData();
    installDemoPrerequisites();
    $directory = demoAccountsDirectory();
    $path = match ($kind) {
        'existing' => $directory.'/existing.txt',
        'relative' => 'accounts.txt',
        'inside' => storage_path('framework/testing/demo-accounts.txt'),
    };

    if ($kind === 'existing') {
        file_put_contents($path, 'keep me');
    }

    $result = runDemoCommand('moashir:demo:install', $path);

    expect($result['exit'])->toBe(1)
        ->and($result['output'])->toContain('the accounts file')
        ->and(Entity::query()->count())->toBe(0)
        ->and(DemoRecord::query()->count())->toBe(0);

    if ($kind === 'existing') {
        expect(file_get_contents($path))->toBe('keep me');
    } else {
        expect(file_exists($path))->toBeFalse();
    }
})->with(['existing', 'relative', 'inside']);

it('rotates every demo password into a new private file without printing it', function (): void {
    enableDemoData();
    installDemoPrerequisites();
    $directory = demoAccountsDirectory();
    runDemoCommand('moashir:demo:install', $directory.'/installed.txt');
    $before = demoPasswordsIn($directory.'/installed.txt');

    $result = runDemoCommand('moashir:demo:rotate-passwords', $directory.'/rotated.txt');
    $after = demoPasswordsIn($directory.'/rotated.txt');

    expect($result['exit'])->toBe(0)
        ->and($result['output'])->toContain('New passwords issued for 11 demo accounts')
        ->and(fileperms($directory.'/rotated.txt') & 0777)->toBe(0600)
        ->and(array_keys($after))->toEqualCanonicalizing(array_keys($before));

    foreach ($after as $email => $password) {
        $hash = (string) User::query()->where('email', $email)->value('password');

        expect($result['output'])->not->toContain($password)
            ->and($password)->not->toBe($before[$email])
            ->and(Hash::check($password, $hash))->toBeTrue()
            ->and(Hash::check($before[$email], $hash))->toBeFalse();
    }
});

it('refuses to rotate demo passwords without the flag or without demo accounts', function (bool $flag, string $reason, string $message): void {
    Log::spy();
    config()->set('moashir.demo.enabled', $flag);
    $accountsFile = demoAccountsDirectory().'/rotated.txt';

    $result = runDemoCommand('moashir:demo:rotate-passwords', $accountsFile);

    expect($result['exit'])->toBe(1)
        ->and($result['output'])->toContain($message)
        ->and(file_exists($accountsFile))->toBeFalse();
    expectRefusalWasLogged($reason);
})->with([
    'flag not set' => [false, 'flag_not_set', 'APP_DEMO_DATA is not set to true'],
    'nothing installed' => [true, 'not_installed', 'No demo accounts exist'],
]);
