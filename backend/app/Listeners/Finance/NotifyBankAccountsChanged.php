<?php

declare(strict_types=1);

namespace App\Listeners\Finance;

use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Events\Finance\BankAccountsChanged;
use App\Models\Identity\User;
use App\Services\Entities\EntityNotifier;
use App\Support\Finance\FinanceNotices;
use App\Support\ScalarValue;

final readonly class NotifyBankAccountsChanged
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(BankAccountsChanged $event): void
    {
        $recipients = User::query()->permission(PermissionKey::FinanceTransfersReview->value)->pluck('id')
            ->merge(User::query()->role(RoleKey::SystemAdmin->value)->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $this->notifier->toStaffUsers(array_values(array_map(ScalarValue::int(...), $recipients)), FinanceNotices::bankAccountChanged($event->account, $event->change), $event->actorId);
    }
}
