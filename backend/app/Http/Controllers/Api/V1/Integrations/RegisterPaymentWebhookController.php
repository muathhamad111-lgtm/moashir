<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\RegisterPaymentWebhook;
use App\Http\Requests\Integrations\RegisterPaymentWebhookRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class RegisterPaymentWebhookController
{
    public function __invoke(RegisterPaymentWebhookRequest $request, #[CurrentUser] User $actor, RegisterPaymentWebhook $register): JsonResponse
    {
        $webhook = $register->handle($actor, $request->mode(), $request->reason());

        return ApiResponse::data(['mode' => $request->mode()->value, 'webhook_id' => $webhook->id, 'url' => $webhook->url], Response::HTTP_CREATED);
    }
}
