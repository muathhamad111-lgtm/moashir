<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ActivateBankAccount;
use App\Actions\Finance\ListBankAccounts;
use App\Http\Requests\Finance\FinanceReasonRequest;
use App\Http\Resources\Finance\BankAccountResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\BankAccount;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ActivateBankAccountController
{
    public function __invoke(FinanceReasonRequest $request, BankAccount $bankAccount, #[CurrentUser] User $actor, ActivateBankAccount $activate, ListBankAccounts $listAccounts): JsonResponse
    {
        return ApiResponse::resource(new BankAccountResource($listAccounts->present($activate->handle($actor, $bankAccount, $request->reason()))));
    }
}
