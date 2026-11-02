<?php

declare(strict_types=1);

use App\Actions\Files\PurgeOrphanFiles;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\FileVisibility;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Platform\HousekeepingTask;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Audit\Audit;
use App\Models\Files\StoredFile;
use App\Models\Platform\HousekeepingRun;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

function storedFileExists(StoredFile $file): bool
{
    return StoredFile::withTrashed()->whereKey($file->id)->exists();
}

it('removes never-attached files older than the grace period, binary and row, and audits the counts only', function (): void {
    $orphan = storedFileCreatedHoursAgo(25);
    $removedOrphan = storedFileCreatedHoursAgo(30);
    $removedOrphan->delete();
    $recent = storedFileCreatedHoursAgo(2);
    $attached = storedFileCreatedHoursAgo(72);
    $attached->attachable()->associate(systemAdmin())->save();
    $applePay = storedFileCreatedHoursAgo(72, ['purpose' => StoredFilePurpose::ApplePayDomainAssociation, 'visibility' => FileVisibility::PublicAsset, 'content_class' => 'P']);

    $this->artisan('moashir:files:purge-orphans')
        ->expectsOutputToContain(sprintf('Purged 2 orphan file(s) (%d bytes) older than 24 hours.', $orphan->size_bytes + $removedOrphan->size_bytes))
        ->assertSuccessful();

    expect(storedFileExists($orphan))->toBeFalse()
        ->and(storedFileExists($removedOrphan))->toBeFalse()
        ->and(storedFileExists($recent))->toBeTrue()
        ->and(storedFileExists($attached))->toBeTrue()
        ->and(storedFileExists($applePay))->toBeTrue();
    Storage::disk('local')->assertMissing($orphan->path);
    Storage::disk('local')->assertExists($recent->path);
    Storage::disk('local')->assertExists($attached->path);
    Storage::disk('local')->assertExists($applePay->path);

    $run = HousekeepingRun::query()->sole();
    $counts = ['purged_files' => 2, 'purged_bytes' => $orphan->size_bytes + $removedOrphan->size_bytes, 'by_purpose' => ['refund_evidence' => 2], 'grace_hours' => 24];
    expect($run->task)->toBe(HousekeepingTask::OrphanFilePurge)
        ->and($run->counts)->toEqual($counts);

    $audit = Audit::query()->where('event', AuditEvent::OrphanFilesPurged->value)->sole();
    expect($audit->auditable_type)->toBe($run->getMorphClass())
        ->and($audit->auditable_id)->toBe($run->id)
        ->and($audit->new_values)->toEqual($counts)
        ->and((string) json_encode($audit->toArray(), JSON_UNESCAPED_UNICODE))->not->toContain($orphan->original_name)
        ->not->toContain($orphan->path)
        ->not->toContain($orphan->public_id);
});

it('purges quarantined orphan files from the quarantine area', function (): void {
    $file = storedFileCreatedHoursAgo(48, ['path' => 'quarantine/'.fake()->uuid()]);
    Storage::disk('local')->put($file->path, infectedContents());

    app(PurgeOrphanFiles::class)->handle();

    expect(storedFileExists($file))->toBeFalse();
    Storage::disk('local')->assertMissing($file->path);
});

it('records an empty run without an audit when nothing is orphaned', function (): void {
    storedFileCreatedHoursAgo(1);

    $run = app(PurgeOrphanFiles::class)->handle();

    expect($run->counts)->toEqual(['purged_files' => 0, 'purged_bytes' => 0, 'by_purpose' => [], 'grace_hours' => 24])
        ->and(Audit::query()->where('event', AuditEvent::OrphanFilesPurged->value)->exists())->toBeFalse();
});

it('honours the configured grace period', function (): void {
    config()->set('moashir.files.orphan_grace_hours', 48);
    $file = storedFileCreatedHoursAgo(25);

    $this->artisan('moashir:files:purge-orphans')->expectsOutputToContain('Purged 0 orphan file(s) (0 bytes) older than 48 hours.')->assertSuccessful();

    expect(storedFileExists($file))->toBeTrue();
});

it('is safe to run again: the second run finds nothing to purge', function (): void {
    storedFileCreatedHoursAgo(25);

    app(PurgeOrphanFiles::class)->handle();
    $second = app(PurgeOrphanFiles::class)->handle();

    expect($second->counts['purged_files'])->toBe(0)
        ->and(HousekeepingRun::query()->count())->toBe(2)
        ->and(Audit::query()->where('event', AuditEvent::OrphanFilesPurged->value)->count())->toBe(1);
});

it('keeps housekeeping runs append-only', function (): void {
    $run = app(PurgeOrphanFiles::class)->handle();

    expect(fn () => $run->forceFill(['counts' => []])->save())->toThrow(AppendOnlyViolation::class)
        ->and(fn () => $run->delete())->toThrow(AppendOnlyViolation::class);
});
