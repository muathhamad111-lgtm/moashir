<?php

declare(strict_types=1);

namespace App\Listeners\Platform;

use App\Enums\Notifications\NotificationCode;
use App\Services\Integrations\IntegrationNotifier;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

final readonly class AlertAdministratorsOfBackupProblem
{
    private const string SCOPE = 'backup';

    public function __construct(private IntegrationNotifier $notifier) {}

    public function handle(UnhealthyBackupWasFound|BackupHasFailed $event): void
    {
        $this->notifier->notifyOnce(NotificationCode::BackupUnhealthy, self::SCOPE);
    }
}
