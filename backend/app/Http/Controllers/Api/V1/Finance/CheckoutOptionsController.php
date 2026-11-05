<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ListCheckoutOptions;
use App\Http\Resources\Finance\CheckoutOptionsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class CheckoutOptionsController
{
    public function __invoke(#[CurrentUser] User $actor, ListCheckoutOptions $listOptions): JsonResponse
    {
        return ApiResponse::resource(new CheckoutOptionsResource($listOptions->handle($actor)));
    }
}
