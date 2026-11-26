<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;
use App\Services\Finance\BankTransferQuery;
use App\Support\Finance\FinanceLinks;

final readonly class BankTransferReviewActionItems implements ActionItemSource
{
    public function __construct(private BankTransferQuery $transfers) {}

    public function isAvailableTo(User $user): bool
    {
        return $user->can(PermissionKey::FinanceTransfersReview->value);
    }

    public function itemsFor(User $user): array
    {
        $count = $this->transfers->awaitingReviewCount();

        return $count > 0 ? [new ActionItem('review_bank_transfers', $count, ActionSeverity::Warning, FinanceLinks::bankTransfers())] : [];
    }
}
