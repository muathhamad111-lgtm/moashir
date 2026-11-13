<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ListCheckoutOptions;
use App\Actions\Finance\QuoteCheckoutPricing;
use App\Http\Requests\Finance\CheckoutOptionsRequest;
use App\Http\Resources\Finance\CheckoutOptionsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class CheckoutOptionsController
{
    public function __invoke(CheckoutOptionsRequest $request, #[CurrentUser] User $actor, ListCheckoutOptions $listOptions, QuoteCheckoutPricing $quote): JsonResponse
    {
        $pricing = $quote->handle($request->purpose(), $request->awardCycleId());

        return ApiResponse::resource(new CheckoutOptionsResource($listOptions->handle($actor, $pricing)));
    }
}
