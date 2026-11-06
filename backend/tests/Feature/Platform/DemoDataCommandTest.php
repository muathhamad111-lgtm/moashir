<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Entities\EntityStatus;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\TermsKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Platform\DemoRecordKind;
use App\Enums\Settings\SettingKey;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Cms\CmsPage;
use App\Models\Entities\Entity;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Models\Insights\MaturityInterpretation;
use App\Models\Kpis\Kpi;
use App\Models\Platform\DemoRecord;
use App\Services\Cms\LegalDocumentSource;
use App\Services\Settings\SettingsStore;
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

it('refuses demo data outside local and testing unless the environment opted in to the demo posture', function (string $environment): void {
    Log::spy();
    enableDemoData();
    installDemoPrerequisites();
    app()['env'] = $environment;
    $accountsFile = demoAccountsDirectory().'/accounts.txt';

    $result = runDemoCommand('moashir:demo:install', $accountsFile);

    expect($result['exit'])->toBe(1)
        ->and($result['output'])->toContain('MOASHIR_DEMO is not true')
        ->and(file_exists($accountsFile))->toBeFalse()
        ->and(DemoRecord::query()->count())->toBe(0);
    expectRefusalWasLogged('posture_not_demo');
})->with(['production', 'staging']);

it('installs demo data in production only with the demo posture opt-in', function (): void {
    enableDemoData();
    config()->set('moashir.demo.posture', true);
    installDemoPrerequisites();
    app()['env'] = 'production';

    expect(runDemoCommand('moashir:demo:install', demoAccountsDirectory().'/accounts.txt')['exit'])->toBe(0)
        ->and(DemoRecord::query()->count())->toBeGreaterThan(0);
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
        ->and($result['output'])->toContain('Demo data installed: 8 staff accounts, 3 entities with their owner accounts, 4 TEST-mode payments')
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
        ->and(Payment::query()->where('status', PaymentStatus::Paid)->count())->toBe(6)
        ->and(Payment::query()->where('status', PaymentStatus::UnderReview)->count())->toBe(1)
        ->and(Payment::query()->where('status', PaymentStatus::Rejected)->count())->toBe(1)
        ->and(PaymentReceipt::query()->pluck('number')->unique()->all())->toBe([null])
        ->and(PaymentReceipt::query()->count())->toBe(6)
        ->and(PaymentReceipt::query()->where('is_test', false)->exists())->toBeFalse()
        ->and(BankAccount::query()->sole()->iban)->toBe(EXAMPLE_IBAN)
        ->and(BankAccount::query()->sole()->bank_name->ar)->toContain('مثال')
        ->and(app(SettingsStore::class)->get(SettingKey::PaymentChannels))->toBe(['electronic', 'bank_transfer']);

    $paid = Payment::query()->where('status', PaymentStatus::Paid)->where('channel', PaymentChannel::Electronic)->whereNotIn('id', Assessment::query()->where('status', 'completed')->select('payment_id'))->sole();
    $paidTransfer = Payment::query()->where('status', PaymentStatus::Paid)->where('channel', PaymentChannel::BankTransfer)->sole();
    $cycle = AwardCycle::query()->sole();

    expect(Assessment::query()->where('entity_id', $paid->entity_id)->where('path', AssessmentPath::Accredited)->exists())->toBeTrue()
        ->and(Assessment::query()->where('entity_id', $paidTransfer->entity_id)->where('path', AssessmentPath::Accredited)->exists())->toBeTrue()
        ->and(Payment::query()->where('entity_id', $paidTransfer->entity_id)->where('status', PaymentStatus::Rejected)->exists())->toBeTrue()
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

it('installs published public content and KPIs with the demo so the demo site is not in maintenance', function (): void {
    enableDemoData();
    installDemoPrerequisites();

    $result = runDemoCommand('moashir:demo:install', demoAccountsDirectory().'/accounts.txt');

    expect($result['exit'])->toBe(0)
        ->and($result['output'])->toContain('6 published content pages and 8 published KPIs');
    $this->getJson('/api/v1/content/status')->assertJsonPath('data.maintenance', false);
    $this->getJson('/api/v1/content/home')
        ->assertJsonPath('data.sections.0.type', 'hero')
        ->assertJsonPath('data.site_settings.contact.email', 'info@moashir.example.sa');
    $this->getJson('/api/v1/content/pages/lenses')->assertOk()->assertJsonCount(2, 'data.sections');
    $this->getJson('/api/v1/kpis/featured')->assertOk()->assertJsonCount(6, 'data');
});

it('adds the demo content to an installed demo once and keeps existing content on a second run', function (): void {
    Log::spy();
    enableDemoData();
    installDemoPrerequisites();
    runDemoCommand('moashir:demo:install', demoAccountsDirectory().'/accounts.txt');
    $home = CmsPage::query()->where('key', 'home')->sole();
    $sections = $home->sections()->count();

    expect(Artisan::call('moashir:demo:install', ['--content-only' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('0 published content pages and 0 published KPIs')
        ->and($home->sections()->count())->toBe($sections)
        ->and(Kpi::query()->count())->toBe(8);
});

it('refuses the content-only option without the flag or without an installed demo', function (): void {
    Log::spy();

    expect(Artisan::call('moashir:demo:install', ['--content-only' => true]))->toBe(1);
    expectRefusalWasLogged('flag_not_set');

    enableDemoData();
    expect(Artisan::call('moashir:demo:install', ['--content-only' => true]))->toBe(1)
        ->and(Kpi::query()->exists())->toBeFalse();
});

it('replaces the demo home page with the current demo content on request', function (): void {
    enableDemoData();
    installDemoPrerequisites();
    runDemoCommand('moashir:demo:install', demoAccountsDirectory().'/accounts.txt');
    $home = CmsPage::query()->where('key', 'home')->sole();
    $home->sections()->orderBy('sort_order')->firstOrFail()->forceFill(['title' => ['ar' => 'عنوان قديم', 'en' => 'Old title']])->save();

    expect(Artisan::call('moashir:demo:refresh-home'))->toBe(0)
        ->and(Artisan::output())->toContain('replaced with the current demo content');

    $this->getJson('/api/v1/content/home')->assertOk()
        ->assertJsonPath('data.sections.0.title.ar', 'قِس تميّزك المؤسسي،')
        ->assertJsonPath('data.sections.4.meta.variant', 'journey');
    expect($home->sections()->count())->toBe(9)
        ->and($home->sections()->onlyTrashed()->count())->toBeGreaterThan(0);
});

it('refuses to refresh the demo home page without the flag or an installed demo', function (): void {
    Log::spy();

    expect(Artisan::call('moashir:demo:refresh-home'))->toBe(1);
    expectRefusalWasLogged('flag_not_set');

    enableDemoData();
    expect(Artisan::call('moashir:demo:refresh-home'))->toBe(1)
        ->and(CmsPage::query()->where('key', 'home')->sole()->sections()->count())->toBe(0);
});

it('publishes the legal drafts and the accessibility page with the demo and keeps a second run unchanged', function (): void {
    enableDemoData();
    installDemoPrerequisites();

    $result = runDemoCommand('moashir:demo:install', demoAccountsDirectory().'/accounts.txt');

    expect($result['exit'])->toBe(0);
    $terms = TermsVersion::query()->current()->ofKind(TermsKind::Terms)->sole();
    expect($terms->version)->toBe(LegalDocumentSource::SEED_VERSION)
        ->and($terms->acceptances()->count())->toBe(3)
        ->and(TermsVersion::query()->current()->ofKind(TermsKind::Privacy)->sole()->version)->toBe(LegalDocumentSource::SEED_VERSION);

    $this->getJson('/api/v1/content/legal/privacy')->assertOk()
        ->assertJsonPath('data.title.en', 'Privacy Policy — Moashir')
        ->assertJsonPath('data.body.ar.0.text', 'مسودة للمراجعة القانونية — آخر تحديث: [[تاريخ الاعتماد]]');
    $this->getJson('/api/v1/content/pages/accessibility')->assertOk()
        ->assertJsonCount(13, 'data.sections')
        ->assertJsonPath('data.sections.1.title.ar', '1. التزامنا')
        ->assertJsonPath('data.sections.2.blocks.en.0.type', 'list');

    expect(Artisan::call('moashir:demo:install', ['--content-only' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('0 legal texts and 0 legal pages published from the legal drafts')
        ->and(TermsVersion::query()->count())->toBe(2);
});

it('installs fictional accredited and self results that show every excellence-tools case', function (): void {
    enableDemoData();
    installDemoPrerequisites();
    runDemoCommand('moashir:demo:install', demoAccountsDirectory().'/accounts.txt');
    [$gold, $selfOnly, $mixed] = Entity::query()->orderBy('id')->get()->all();

    $goldOverview = switchTo(ownerOf($gold))->getJson('/api/v1/entities/me/insights')->assertOk()->json('data');
    $goldResult = $goldOverview['selected']['assessment_id'];

    expect($goldOverview['selected']['certificate']['level'])->toBe('gold')
        ->and(MaturityInterpretation::query()->whereNull('main_criterion_code')->whereNotNull('text')->count())->toBe(4);
    switchTo(ownerOf($gold))->getJson("/api/v1/assessments/{$goldResult}/insights/executive-summary")->assertOk()
        ->assertJsonPath('data.change.previous.certificate.level', 'silver')
        ->assertJsonPath('data.assessor_summary.version', 1);
    switchTo(ownerOf($selfOnly))->getJson('/api/v1/entities/me/insights')->assertOk()->assertJsonPath('data.empty_state.reason', 'only_self');
    switchTo(ownerOf($mixed))->getJson('/api/v1/entities/me/insights')->assertOk()
        ->assertJsonPath('data.selected.certificate.level', null)
        ->assertJsonPath('data.selected.certificate.status', 'issued')
        ->assertJsonPath('data.exclusions.0.reason', 'certificate_revoked');
});
