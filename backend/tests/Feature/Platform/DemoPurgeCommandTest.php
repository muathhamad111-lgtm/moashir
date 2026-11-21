<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Platform\DemoRecordKind;
use App\Enums\Platform\HousekeepingTask;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Certificates\Certificate;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Identity\User;
use App\Models\Platform\DemoRecord;
use App\Models\Platform\HousekeepingRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

function installedDemo(): void
{
    enableDemoData();
    config()->set('moashir.demo.posture', true);
    installDemoPrerequisites();

    expect(runDemoCommand('moashir:demo:install', demoAccountsDirectory().'/accounts.txt')['exit'])->toBe(0);
}

/**
 * @return array{exit: int, output: string}
 */
function runDemoPurge(bool $force): array
{
    $exit = Artisan::call('moashir:demo:purge', $force ? ['--force' => true] : []);

    return ['exit' => $exit, 'output' => Artisan::output()];
}

/**
 * @return list<int>
 */
function registeredIdsOf(DemoRecordKind $kind): array
{
    return array_values(DemoRecord::query()->where('kind', $kind)->orderBy('id')->pluck('record_id')->map(static fn (mixed $id): int => (int) $id)->all());
}

it('refuses to preview or purge outside the demo posture and logs the refusal', function (bool $force): void {
    Log::spy();
    installedDemo();
    config()->set('moashir.demo.posture', false);
    $records = DemoRecord::query()->count();

    $result = runDemoPurge($force);

    expect($result['exit'])->toBe(1)
        ->and($result['output'])->toContain('MOASHIR_DEMO is not true')
        ->and(DemoRecord::query()->count())->toBe($records);
    expectRefusalWasLogged('purge_posture_not_demo');
})->with(['dry run' => false, 'forced' => true]);

it('prints a dry-run summary by default and removes nothing', function (): void {
    installedDemo();
    $entities = Entity::query()->count();
    $payments = Payment::query()->count();

    $result = runDemoPurge(false);

    expect($result['exit'])->toBe(0)
        ->and($result['output'])->toContain('Dry run: nothing was removed')
        ->and($result['output'])->toMatch('/entities\s.*\s3\b/')
        ->and(DemoRecord::query()->count())->toBeGreaterThan(0)
        ->and(Entity::query()->count())->toBe($entities)
        ->and(Payment::query()->count())->toBe($payments)
        ->and(Audit::query()->where('event', AuditEvent::DemoPurged->value)->exists())->toBeFalse();
});

it('purges every registered demo record with its dependants and files, audits the counts and leaves real records untouched', function (): void {
    installedDemo();
    $realEntity = entityWithOwner();
    $realOwner = ownerOf($realEntity);
    $realStaff = staffUser(RoleKey::FinanceOfficer);
    $demoEntityIds = registeredIdsOf(DemoRecordKind::Entity);
    $demoUserIds = registeredIdsOf(DemoRecordKind::User);
    $demoFiles = StoredFile::withTrashed()->get(['id', 'disk', 'path']);
    $demoAssessmentIds = Assessment::query()->whereIn('entity_id', $demoEntityIds)->pluck('id')->all();

    foreach ($demoFiles as $file) {
        Storage::disk($file->disk)->assertExists($file->path);
    }

    expect($demoFiles)->not->toBeEmpty()
        ->and(Certificate::query()->count())->toBeGreaterThan(0)
        ->and(AwardCycle::query()->count())->toBe(1);

    $result = runDemoPurge(true);

    expect($result['exit'])->toBe(0)
        ->and($result['output'])->toContain('Demo data purged.')
        ->and(DemoRecord::query()->count())->toBe(0)
        ->and(Entity::withTrashed()->whereKey($demoEntityIds)->exists())->toBeFalse()
        ->and(Assessment::query()->whereKey($demoAssessmentIds)->exists())->toBeFalse()
        ->and(Payment::query()->whereIn('entity_id', $demoEntityIds)->exists())->toBeFalse()
        ->and(PaymentReceipt::query()->count())->toBe(0)
        ->and(Certificate::query()->count())->toBe(0)
        ->and(AwardCycle::query()->count())->toBe(0)
        ->and(StoredFile::withTrashed()->whereKey($demoFiles->modelKeys())->exists())->toBeFalse()
        ->and(User::query()->whereKey($demoUserIds)->exists())->toBeFalse()
        ->and(User::withTrashed()->whereKey($demoUserIds)->where('email', 'like', 'demo.%')->exists())->toBeFalse()
        ->and($realEntity->refresh()->exists)->toBeTrue()
        ->and($realOwner->refresh()->trashed())->toBeFalse()
        ->and($realStaff->refresh()->trashed())->toBeFalse();

    foreach ($demoFiles as $file) {
        Storage::disk($file->disk)->assertMissing($file->path);
    }

    $run = HousekeepingRun::query()->where('task', HousekeepingTask::DemoPurge)->sole();
    $audit = Audit::query()->where('event', AuditEvent::DemoPurged->value)->sole();

    expect($run->counts['entities'])->toBe(3)
        ->and($run->counts['users_deleted'] + $run->counts['users_retired'])->toBe(count($demoUserIds))
        ->and($run->counts['register_records'])->toBeGreaterThan(count($demoUserIds))
        ->and($run->counts['award_cycles'])->toBe(1)
        ->and($audit->auditable_id)->toBe($run->id)
        ->and($audit->new_values)->toBe($run->counts);
});

it('retires a demo staff account that real records still reference instead of deleting it', function (): void {
    installedDemo();
    $admin = User::query()->where('email', 'demo.system.admin@moashir.example.sa')->sole();

    runDemoPurge(true);

    $retired = User::withTrashed()->find($admin->id);
    expect($retired)->not->toBeNull()
        ->and($retired?->trashed())->toBeTrue()
        ->and($retired?->email)->toEndWith('@erased.invalid')
        ->and($retired?->password)->toBeNull()
        ->and($retired?->roles)->toBeEmpty()
        ->and(HousekeepingRun::query()->sole()->counts['users_retired'])->toBeGreaterThanOrEqual(1);
});

it('is a no-op when run again', function (): void {
    installedDemo();
    runDemoPurge(true);
    $users = User::withTrashed()->count();

    $result = runDemoPurge(true);

    expect($result['exit'])->toBe(0)
        ->and($result['output'])->toContain('Nothing to purge')
        ->and(User::withTrashed()->count())->toBe($users)
        ->and(HousekeepingRun::query()->where('task', HousekeepingTask::DemoPurge)->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::DemoPurged->value)->count())->toBe(1);
});

it('restores every append-only guard after a purge batch', function (): void {
    installedDemo();

    runDemoPurge(true);

    $disabled = DB::scalar("select count(*) from pg_trigger where not tgisinternal and tgenabled = 'D'");
    expect($disabled)->toBe(0);
});
