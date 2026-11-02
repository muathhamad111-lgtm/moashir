<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\Finance\PaymentPurpose;
use App\Enums\Identity\PermissionKey;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use Illuminate\Auth\Access\Response;

final class PaymentPolicy
{
    public function viewAnyOwn(User $actor): bool
    {
        return $actor->entity_id !== null && $actor->can(PermissionKey::PaymentsOwnView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->entity_id !== null && $actor->can(PermissionKey::PaymentsOwnCreate->value);
    }

    public function createFor(User $actor, PaymentPurpose $purpose): bool
    {
        return $this->create($actor) && $actor->can($purpose->requiredPermission()->value);
    }

    public function viewOwn(User $actor, Payment $payment): Response
    {
        if (! $actor->can(PermissionKey::PaymentsOwnView->value)) {
            return Response::deny();
        }

        return $payment->isOwnedBy($actor->entity_id) ? Response::allow() : Response::denyAsNotFound();
    }

    public function verify(User $actor, Payment $payment): Response
    {
        if (! $actor->can(PermissionKey::PaymentsOwnCreate->value)) {
            return Response::deny();
        }

        return $payment->isOwnedBy($actor->entity_id) ? Response::allow() : Response::denyAsNotFound();
    }

    public function viewOperations(User $actor): bool
    {
        return $actor->can(PermissionKey::FinanceOperationsView->value);
    }

    public function export(User $actor): bool
    {
        return $actor->can(PermissionKey::FinanceOperationsExport->value);
    }

    public function viewEvents(User $actor): bool
    {
        return $actor->can(PermissionKey::SettingsPaymentManage->value);
    }

    public function recordRefund(User $actor): bool
    {
        return $actor->can(PermissionKey::FinanceRefundsManage->value);
    }

    public function manualSettle(): bool
    {
        return false;
    }
}
