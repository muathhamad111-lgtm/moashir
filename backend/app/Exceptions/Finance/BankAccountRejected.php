<?php

declare(strict_types=1);

namespace App\Exceptions\Finance;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class BankAccountRejected extends ApiException
{
    public static function notSelectable(): self
    {
        return self::fieldError('bank_account.not_selectable', 'bank_account_id');
    }

    /**
     * @param  list<array<string, mixed>>  $transfers
     */
    public static function pendingTransfers(array $transfers): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'bank_account.pending_transfers', [], [], ['transfers' => $transfers]));
    }

    public static function lastActiveAccount(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'bank_account.last_active_account'));
    }

    public static function limitReached(int $max): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'bank_account.limit_reached', [], [], ['max_active_accounts' => $max]));
    }

    public static function payeeFieldsImmutable(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'bank_account.payee_fields_immutable'));
    }

    public static function ibanTaken(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'validation_failed', ['iban' => [__('validation.custom.iban.unique')]]));
    }

    private static function fieldError(string $code, string $field): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, $code, [$field => [__('errors.'.$code)]]));
    }
}
