<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ListBankAccounts;
use App\Actions\Finance\ReorderBankAccounts;
use App\Http\Requests\Finance\ReorderBankAccountsRequest;
use App\Http\Resources\Finance\BankAccountResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ReorderBankAccountsController
{
    public function __invoke(ReorderBankAccountsRequest $request, #[CurrentUser] User $actor, ReorderBankAccounts $reorder, ListBankAccounts $listAccounts): JsonResponse
    {
        $reorder->handle($actor, $request->orderedIds(), $request->reason());

        return ApiResponse::resource(BankAccountResource::collection($listAccounts->all()));
    }
}
