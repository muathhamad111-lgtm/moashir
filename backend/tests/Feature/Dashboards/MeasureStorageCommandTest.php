<?php

declare(strict_types=1);

use App\Models\Platform\StorageMeasurement;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Date::setTestNow('2026-11-17 23:15:00');
    config(['moashir.files.disk' => 'files-under-test']);
    Storage::fake('files-under-test');
});

it('records the bytes and files on the files disk', function (): void {
    Storage::disk('files-under-test')->put('evidence/2026/11/first.pdf', str_repeat('a', 1200));
    Storage::disk('files-under-test')->put('logos/second.png', str_repeat('b', 300));

    $this->artisan('moashir:storage:measure')->expectsOutputToContain('Measured 2 file(s) taking 1500 bytes.')->assertSuccessful();

    $measurement = StorageMeasurement::query()->sole();

    expect($measurement->used_bytes)->toBe(1500)
        ->and($measurement->file_count)->toBe(2)
        ->and($measurement->measured_on->toDateString())->toBe('2026-11-18')
        ->and($measurement->measured_at->toIso8601String())->toBe('2026-11-17T23:15:00+00:00');
});

it('keeps one measurement per Riyadh day when run again', function (): void {
    Storage::disk('files-under-test')->put('first.pdf', str_repeat('a', 100));
    $this->artisan('moashir:storage:measure')->assertSuccessful();
    Storage::disk('files-under-test')->put('second.pdf', str_repeat('b', 50));
    Date::setTestNow('2026-11-18 08:00:00');

    $this->artisan('moashir:storage:measure')->assertSuccessful();

    expect(StorageMeasurement::query()->count())->toBe(1)
        ->and(StorageMeasurement::query()->sole()->used_bytes)->toBe(150);

    Date::setTestNow('2026-11-18 22:00:00');
    $this->artisan('moashir:storage:measure')->assertSuccessful();

    expect(StorageMeasurement::query()->count())->toBe(2);
});

it('records an empty disk as zero', function (): void {
    $this->artisan('moashir:storage:measure')->assertSuccessful();

    expect(StorageMeasurement::query()->sole()->only(['used_bytes', 'file_count']))->toBe(['used_bytes' => 0, 'file_count' => 0]);
});

it('measures once a night without overlapping runs', function (): void {
    $events = collect(app(Schedule::class)->events())->filter(static fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'moashir:storage:measure'));

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('15 2 * * *')
        ->and($events->first()?->withoutOverlapping)->toBeTrue();
});
