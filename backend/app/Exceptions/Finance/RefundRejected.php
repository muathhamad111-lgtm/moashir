<?php

declare(strict_types=1);

namespace App\Exceptions\Finance;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class RefundRejected extends ApiException
{
    public static function amountMustEqualTotal(string $total): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'refund.amount_must_equal_total', [
            'amount' => [__('errors.refund.amount_must_equal_total', ['total' => $total])],
        ], [], ['payment_total' => $total]));
    }

    public static function dateBeforePayment(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'refund.date_before_payment', [
            'refunded_on' => [__('errors.refund.date_before_payment')],
        ]));
    }

    public static function alreadyRecorded(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'refund.already_recorded'));
    }
}
