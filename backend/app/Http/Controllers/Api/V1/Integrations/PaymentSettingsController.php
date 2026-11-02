<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\UpdatePaymentSettings;
use App\Http\Requests\Integrations\UpdatePaymentSettingsRequest;
use App\Http\Resources\Integrations\PaymentSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Integrations\PaymentSettingsViewReader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class PaymentSettingsController
{
    public function show(PaymentSettingsViewReader $reader): JsonResponse
    {
        return ApiResponse::resource(new PaymentSettingsResource($reader->read()));
    }

    public function update(UpdatePaymentSettingsRequest $request, #[CurrentUser] User $actor, UpdatePaymentSettings $update, PaymentSettingsViewReader $reader): JsonResponse
    {
        $update->handle($actor, $request->toUpdate());

        return ApiResponse::resource(new PaymentSettingsResource($reader->read()));
    }
}
