<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\SendTestSms;
use App\Http\Requests\Integrations\SendTestSmsRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class SendTestSmsController
{
    public function __invoke(SendTestSmsRequest $request, #[CurrentUser] User $actor, SendTestSms $send): JsonResponse
    {
        $result = $send->handle($actor, $request->recipient(), $request->provider());

        return ApiResponse::data([
            'provider' => $result->provider?->value,
            'status' => $result->status->value,
            'provider_message_id' => $result->providerMessageId,
        ]);
    }
}
