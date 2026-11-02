<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\Identity\PermissionKey;
use App\Models\Finance\FinanceExport;
use App\Models\Identity\User;
use Illuminate\Auth\Access\Response;

final class FinanceExportPolicy
{
    public function view(User $actor, FinanceExport $export): Response
    {
        if (! $actor->can(PermissionKey::FinanceOperationsExport->value)) {
            return Response::deny();
        }

        return $export->isRequestedBy($actor->id) ? Response::allow() : Response::denyAsNotFound();
    }

    public function download(User $actor, FinanceExport $export): Response
    {
        return $this->view($actor, $export);
    }
}
