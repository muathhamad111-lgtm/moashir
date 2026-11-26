<?php

declare(strict_types=1);

namespace App\Exceptions\Paths;

use App\Data\Paths\PathPricing;
use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use App\Support\Paths\PathPricingPresenter;
use Illuminate\Http\Response;

final class PathRejected extends ApiException
{
    public static function unavailable(PaymentPurpose $path, PathPricing $pricing): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'path.unavailable', [], [], [
            'path' => $path->value,
            'availability' => $pricing->availability->value,
        ]));
    }

    public static function paymentRequired(PaymentPurpose $path, PathPricing $pricing, ?int $awardCycleId = null): self
    {
        $meta = [
            'path' => $path->value,
            'purpose' => $path->value,
            'pricing' => PathPricingPresenter::present($pricing),
        ];

        return new self(new ApiError(Response::HTTP_CONFLICT, 'path.payment_required', [], [], $path === PaymentPurpose::Award ? [...$meta, 'award_cycle_id' => $awardCycleId] : $meta));
    }
}
