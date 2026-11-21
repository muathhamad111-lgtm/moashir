<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Settings\UpdatePaymentChannels;
use App\Http\Requests\Settings\UpdatePaymentChannelsRequest;
use App\Http\Resources\Finance\PaymentChannelsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Finance\PaymentChannelsOverview;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class PaymentChannelsController
{
    public function show(PaymentChannelsOverview $overview): JsonResponse
    {
        return ApiResponse::resource(new PaymentChannelsResource($overview->read()));
    }

    public function update(UpdatePaymentChannelsRequest $request, #[CurrentUser] User $actor, UpdatePaymentChannels $update, PaymentChannelsOverview $overview): JsonResponse
    {
        $update->handle($actor, $request->toUpdate());

        return ApiResponse::resource(new PaymentChannelsResource($overview->read()));
    }
}
