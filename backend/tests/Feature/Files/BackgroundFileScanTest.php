<?php

declare(strict_types=1);

use App\Actions\Files\ScanPendingStoredFile;
use App\Data\Files\ScanVerdict;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Files\FileScannerUnavailable;
use App\Integrations\Fakes\FakeFileScanner;
use App\Jobs\Files\ScanStoredFileJob;
use App\Models\Audit\Audit;
use App\Models\Files\StoredFile;
use App\Notifications\Entities\EntityNotification;
use App\Services\Files\StoredFileScanner;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

function runScanJob(StoredFile $file, int $attempt = 1): ScanStoredFileJob
{
    $job = new ScanStoredFileJob($file->id)->withFakeQueueInteractions();
    $job->job->attempts = $attempt;
    $job->handle(app(ScanPendingStoredFile::class));

    return $job;
}

/**
 * @return list<string>
 */
function fileNoticeCodesSentTo(object $notifiable): array
{
    $codes = [];

    Notification::assertSentTo($notifiable, EntityNotification::class, static function (EntityNotification $notification) use (&$codes): bool {
        $codes[] = $notification->notice->code->value;

        return true;
    });

    return $codes;
}

function scanAudits(AuditEvent $event): int
{
    return Audit::query()->where('event', $event->value)->count();
}

it('marks a pending file clean once the scanner answers, which makes it available', function (): void {
    $file = storedTestFile();

    $job = runScanJob($file);

    $job->assertNotReleased();
    $job->assertNotFailed();
    $file->refresh();
    expect($file->malware_scan_status)->toBe(MalwareScanStatus::Clean)
        ->and($file->isScannedClean())->toBeTrue()
        ->and($file->malware_scanned_at)->not->toBeNull()
        ->and($file->scan_engine)->toBe(FakeFileScanner::ENGINE)
        ->and($file->quarantined_at)->toBeNull();

    $audit = Audit::query()->where('event', AuditEvent::FileScanned->value)->sole();
    expect($audit->old_values)->toBe(['malware_scan_status' => 'pending'])
        ->and($audit->new_values)->toMatchArray(['malware_scan_status' => 'clean', 'scan_engine' => FakeFileScanner::ENGINE]);
    Notification::assertNothingSent();
});

it('keeps the file pending and retries with a growing backoff while the scanner is down', function (int $attempt, int $delay): void {
    fileScanner()->makeUnavailable();
    $file = storedTestFile();

    $job = runScanJob($file, $attempt);

    $job->assertReleased(delay: $delay);
    $job->assertNotFailed();
    expect($file->refresh()->malware_scan_status)->toBe(MalwareScanStatus::Pending)
        ->and($file->malware_scanned_at)->toBeNull()
        ->and(scanAudits(AuditEvent::FileScanned))->toBe(0);
    Notification::assertNothingSent();
})->with([
    'first attempt' => [1, 60],
    'second attempt' => [2, 300],
    'third attempt' => [3, 900],
    'fourth attempt' => [4, 1800],
    'fifth attempt' => [5, 3600],
]);

it('scans the file on a later attempt once the scanner is back', function (): void {
    fileScanner()->makeUnavailable();
    $file = storedTestFile();
    runScanJob($file)->assertReleased(delay: 60);

    fileScanner()->makeAvailable();
    runScanJob($file, 2)->assertNotReleased();

    expect($file->refresh()->malware_scan_status)->toBe(MalwareScanStatus::Clean)
        ->and(fileScanner()->scannedCount())->toBe(2);
});

it('fails the job after the last attempt, keeps the file unavailable and alerts the System Administrators once', function (): void {
    fileScanner()->makeUnavailable();
    $admin = systemAdmin();
    $financeOfficer = financeOfficer();
    $first = storedTestFile();
    $second = storedTestFile();

    $job = runScanJob($first, 6);

    $job->assertNotReleased();
    $job->assertFailedWith(FileScannerUnavailable::class);
    expect($first->refresh()->malware_scan_status)->toBe(MalwareScanStatus::Pending);

    $job->failed(FileScannerUnavailable::unreachable());
    new ScanStoredFileJob($second->id)->failed(null);

    expect($first->refresh()->malware_scan_status)->toBe(MalwareScanStatus::Failed)
        ->and($first->isScannedClean())->toBeFalse()
        ->and($first->scan_engine)->toBe('scan-abandoned')
        ->and($second->refresh()->malware_scan_status)->toBe(MalwareScanStatus::Failed)
        ->and(fileNoticeCodesSentTo($admin))->toBe([NotificationCode::ExternalServiceDown->value]);
    Notification::assertNotSentTo($financeOfficer, EntityNotification::class);
});

it('quarantines an infected file and alerts the System Administrators in the platform', function (): void {
    $admin = systemAdmin();
    $file = storedTestFile(contents: infectedContents());
    $originalPath = $file->path;

    runScanJob($file)->assertNotReleased();

    $file->refresh();
    expect($file->malware_scan_status)->toBe(MalwareScanStatus::Infected)
        ->and($file->quarantined_at)->not->toBeNull()
        ->and($file->path)->toBe('quarantine/'.$file->public_id)
        ->and($file->isScannedClean())->toBeFalse();
    Storage::disk('local')->assertMissing($originalPath);
    Storage::disk('local')->assertExists('quarantine/'.$file->public_id);

    expect(Audit::query()->where('event', AuditEvent::FileScanned->value)->sole()->new_values)
        ->toMatchArray(['malware_scan_status' => 'infected', 'signature' => 'Moashir.Test.Signature'])
        ->and(Audit::query()->where('event', AuditEvent::FileQuarantined->value)->sole()->new_values)
        ->toEqual(['quarantined' => true, 'purpose' => 'refund_evidence', 'scan_engine' => FakeFileScanner::ENGINE]);

    Notification::assertSentTo($admin, EntityNotification::class, static fn (EntityNotification $notification, array $channels): bool => $notification->notice->code === NotificationCode::RequestProcessingFailed
        && ! $notification->notice->viaMail
        && str_contains($notification->notice->body->en, $file->public_id)
        && ! str_contains($notification->notice->body->ar.$notification->notice->body->en, 'إيصال-تحويل'));
});

it('is idempotent: a second run scans nothing, quarantines nothing and alerts nobody again', function (): void {
    $admin = systemAdmin();
    $file = storedTestFile(contents: infectedContents());

    runScanJob($file);
    runScanJob($file)->assertNotReleased();

    expect(fileScanner()->scannedCount())->toBe(1)
        ->and(scanAudits(AuditEvent::FileScanned))->toBe(1)
        ->and(scanAudits(AuditEvent::FileQuarantined))->toBe(1)
        ->and(fileNoticeCodesSentTo($admin))->toBe([NotificationCode::RequestProcessingFailed->value]);
});

it('completes a quarantine that was interrupted after the verdict', function (): void {
    systemAdmin();
    $file = storedTestFile(['malware_scan_status' => MalwareScanStatus::Infected, 'malware_scanned_at' => Date::now(), 'scan_engine' => 'clamav'], infectedContents());

    runScanJob($file);

    expect($file->refresh()->quarantined_at)->not->toBeNull()
        ->and(fileScanner()->scannedCount())->toBe(0)
        ->and(scanAudits(AuditEvent::FileQuarantined))->toBe(1);
    Storage::disk('local')->assertExists('quarantine/'.$file->public_id);
});

it('leaves missing, removed and already scanned files alone', function (): void {
    $clean = storedTestFile(['malware_scan_status' => MalwareScanStatus::Clean, 'malware_scanned_at' => Date::now(), 'scan_engine' => 'clamav']);
    $removed = storedTestFile();
    $removed->delete();

    new ScanStoredFileJob(999_999)->withFakeQueueInteractions()->handle(app(ScanPendingStoredFile::class));
    runScanJob($clean)->assertNotReleased();
    runScanJob($removed)->assertNotReleased();

    expect(fileScanner()->scannedCount())->toBe(0)
        ->and($clean->refresh()->malware_scan_status)->toBe(MalwareScanStatus::Clean)
        ->and(StoredFile::withTrashed()->find($removed->id)?->malware_scan_status)->toBe(MalwareScanStatus::Pending)
        ->and(scanAudits(AuditEvent::FileScanned))->toBe(0);
});

it('never overwrites a verdict that another process recorded first', function (): void {
    $file = storedTestFile();
    $scanner = app(StoredFileScanner::class);

    expect($scanner->record($file, ScanVerdict::clean('clamav')))->toBeTrue()
        ->and($scanner->record(StoredFile::query()->findOrFail($file->id), ScanVerdict::infected('clamav', 'Eicar-Signature')))->toBeFalse()
        ->and($file->refresh()->malware_scan_status)->toBe(MalwareScanStatus::Clean)
        ->and(scanAudits(AuditEvent::FileScanned))->toBe(1);
});

it('is a unique job per file so the sweep never queues the same file twice', function (): void {
    $job = new ScanStoredFileJob(42);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('scan-stored-file:42')
        ->and($job->tries)->toBe(6)
        ->and($job->backoff)->toBe([60, 300, 900, 1800, 3600]);
});

it('queues a background scan for files still pending after ten minutes only', function (): void {
    Queue::fake();
    $stale = storedTestFile(['created_at' => Date::now()->subMinutes(11)]);
    storedTestFile(['created_at' => Date::now()->subMinutes(2)]);
    storedTestFile(['created_at' => Date::now()->subHour(), 'malware_scan_status' => MalwareScanStatus::Clean, 'malware_scanned_at' => Date::now()]);
    storedTestFile(['created_at' => Date::now()->subHour()])->delete();

    $this->artisan('moashir:files:scan-pending')->expectsOutputToContain('Queued the malware scan of 1 file(s).')->assertSuccessful();
    $this->artisan('moashir:files:scan-pending')->assertSuccessful();

    Queue::assertPushed(ScanStoredFileJob::class, 1);
    Queue::assertPushed(ScanStoredFileJob::class, static fn (ScanStoredFileJob $job): bool => $job->storedFileId === $stale->id);
});

it('keeps the synchronous upload scan unchanged: a file scanned during the request is never picked again', function (): void {
    Queue::fake();
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = paidPayment($owner, $gateway);

    switchTo(financeOfficer())->post("/api/v1/finance/payments/{$payment->public_id}/refunds", refundPayload($payment), ['Accept' => 'application/json'])->assertCreated();
    Date::setTestNow(Date::now()->addHour());

    $this->artisan('moashir:files:scan-pending')->expectsOutputToContain('Queued the malware scan of 0 file(s).')->assertSuccessful();
    Queue::assertNotPushed(ScanStoredFileJob::class);
});

it('schedules the pending-scan sweep and the orphan purge', function (): void {
    $events = collect(app(Schedule::class)->events())->map(static fn ($event): string => (string) $event->command);

    expect($events->contains(static fn (string $command): bool => str_contains($command, 'moashir:files:scan-pending')))->toBeTrue()
        ->and($events->contains(static fn (string $command): bool => str_contains($command, 'moashir:files:purge-orphans')))->toBeTrue();
});

it('notifies only active System Administrators about a quarantine', function (): void {
    $admin = systemAdmin();
    $follower = staffUser(RoleKey::RequestFollower);

    runScanJob(storedTestFile(contents: infectedContents()));

    Notification::assertSentTo($admin, EntityNotification::class);
    Notification::assertNotSentTo($follower, EntityNotification::class);
});
