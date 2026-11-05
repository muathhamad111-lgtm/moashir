<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\CreateBankAccount;
use App\Actions\Finance\DeleteBankAccount;
use App\Actions\Finance\ListBankAccounts;
use App\Actions\Finance\UpdateBankAccountNote;
use App\Http\Requests\Finance\FinanceReasonRequest;
use App\Http\Requests\Finance\IndexBankAccountsRequest;
use App\Http\Requests\Finance\StoreBankAccountRequest;
use App\Http\Requests\Finance\UpdateBankAccountRequest;
use App\Http\Resources\Finance\BankAccountResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\BankAccount;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class BankAccountController
{
    public function index(IndexBankAccountsRequest $request, ListBankAccounts $listAccounts): JsonResponse
    {
        return ApiResponse::paginated($listAccounts->handle($request->activeFilter(), $request->perPage()), BankAccountResource::class);
    }

    public function store(StoreBankAccountRequest $request, #[CurrentUser] User $actor, CreateBankAccount $createAccount, ListBankAccounts $listAccounts): JsonResponse
    {
        $account = $createAccount->handle($actor, $request->details(), $request->reason());

        return ApiResponse::resource(new BankAccountResource($listAccounts->present($account)), Response::HTTP_CREATED);
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount, #[CurrentUser] User $actor, UpdateBankAccountNote $updateNote, ListBankAccounts $listAccounts): JsonResponse
    {
        $account = $updateNote->handle($actor, $bankAccount, $request->payerNote(), $request->reason());

        return ApiResponse::resource(new BankAccountResource($listAccounts->present($account)));
    }

    public function destroy(FinanceReasonRequest $request, BankAccount $bankAccount, #[CurrentUser] User $actor, DeleteBankAccount $deleteAccount): Response
    {
        $deleteAccount->handle($actor, $bankAccount, $request->reason());

        return ApiResponse::noContent();
    }
}
