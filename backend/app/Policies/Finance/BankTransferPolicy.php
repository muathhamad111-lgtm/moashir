<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\Identity\PermissionKey;
use App\Models\Finance\BankTransfer;
use App\Models\Identity\User;
use Illuminate\Auth\Access\Response;

final class BankTransferPolicy
{
    public function downloadProof(User $actor, BankTransfer $transfer): Response
    {
        if ($actor->can(PermissionKey::FinanceTransfersReview->value) || $actor->can(PermissionKey::FinanceOperationsView->value)) {
            return Response::allow();
        }

        return $actor->can(PermissionKey::PaymentsOwnView->value) && $transfer->payment->isOwnedBy($actor->entity_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
