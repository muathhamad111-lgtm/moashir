<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

const BACKUP_RETENTION_DAYS = 30;

it('keeps backups daily for the approved 30 days only, with no weekly, monthly or yearly archives', function (): void {
    $strategy = config('backup.cleanup.default_strategy');

    expect($strategy)->toMatchArray([
        'keep_all_backups_for_days' => 7,
        'keep_daily_backups_for_days' => 23,
        'keep_weekly_backups_for_weeks' => 0,
        'keep_monthly_backups_for_months' => 0,
        'keep_yearly_backups_for_years' => 0,
    ])->and($strategy['keep_all_backups_for_days'] + $strategy['keep_daily_backups_for_days'])->toBe(BACKUP_RETENTION_DAYS);
});

it('deletes every archive older than 30 days and keeps one archive per day inside the window', function (): void {
    Notification::fake();
    Storage::fake('backups');
    config()->set('backup.backup.destination.disks', ['backups']);
    $folder = config()->string('backup.backup.name');
    $now = CarbonImmutable::parse('2026-11-04 10:00:00');
    Date::setTestNow($now);

    foreach (range(0, 90) as $daysAgo) {
        $nightly = $now->subDays($daysAgo)->setTime(1, 30);
        Storage::disk('backups')->put($folder.'/'.$nightly->format('Y-m-d-H-i-s').'.zip', 'encrypted archive');
        Storage::disk('backups')->put($folder.'/'.$nightly->addHours(12)->format('Y-m-d-H-i-s').'.zip', 'encrypted archive');
    }

    expect(Artisan::call('backup:clean'))->toBe(0);

    $kept = array_map(
        static fn (string $path): CarbonImmutable => CarbonImmutable::createFromFormat('Y-m-d-H-i-s', basename($path, '.zip')) ?: $now,
        Storage::disk('backups')->files($folder),
    );
    $days = array_unique(array_map(static fn (CarbonImmutable $date): string => $date->toDateString(), $kept));

    expect(min($kept)->greaterThanOrEqualTo($now->subDays(BACKUP_RETENTION_DAYS)))->toBeTrue()
        ->and(count($days))->toBeGreaterThanOrEqual(BACKUP_RETENTION_DAYS)
        ->and(count($kept))->toBeLessThan(2 * BACKUP_RETENTION_DAYS);

    foreach (range(0, BACKUP_RETENTION_DAYS - 1) as $daysAgo) {
        expect($days)->toContain($now->subDays($daysAgo)->toDateString());
    }
});
