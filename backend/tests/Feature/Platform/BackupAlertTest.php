<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

it('alerts every System Administrator once when the backup check finds an unhealthy backup', function (): void {
    Notification::fake();
    $administrator = systemAdmin();
    $assessor = staffUser(RoleKey::Assessor);

    Event::dispatch(new UnhealthyBackupWasFound('local', 'moashir', collect([['check' => 'MaximumAgeInDays', 'message' => 'The latest backup is too old.']])));
    Event::dispatch(new UnhealthyBackupWasFound('local', 'moashir', collect([['check' => 'MaximumAgeInDays', 'message' => 'The latest backup is too old.']])));

    Notification::assertSentToTimes($administrator, EntityNotification::class, 1);
    Notification::assertSentTo($administrator, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::BackupUnhealthy);
    Notification::assertNotSentTo($assessor, EntityNotification::class);
});

it('alerts the System Administrators when a backup run fails', function (): void {
    Notification::fake();
    $administrator = systemAdmin();

    Event::dispatch(new BackupHasFailed(new RuntimeException('disk full'), 'local', 'moashir'));

    Notification::assertSentTo($administrator, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::BackupUnhealthy);
});
