<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\Identity\PermissionKey;
use App\Models\Finance\PaymentReceipt;
use App\Models\Identity\User;
use Illuminate\Auth\Access\Response;

final class PaymentReceiptPolicy
{
    public function download(User $actor, PaymentReceipt $receipt): Response
    {
        if ($actor->can(PermissionKey::FinanceOperationsView->value)) {
            return Response::allow();
        }

        if (! $actor->can(PermissionKey::PaymentsOwnView->value)) {
            return Response::deny();
        }

        return $receipt->payment->isOwnedBy($actor->entity_id) ? Response::allow() : Response::denyAsNotFound();
    }

    public function reissue(User $actor): bool
    {
        return $actor->can(PermissionKey::FinanceReceiptsManage->value);
    }
}
