<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ReceiveGatewayWebhook;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MoyasarWebhookController
{
    public function __invoke(Request $request, ReceiveGatewayWebhook $receiveWebhook): JsonResponse
    {
        $isNew = $receiveWebhook->handle($request->getContent(), $request->ip());

        return ApiResponse::data(['received' => true, 'duplicate' => ! $isNew]);
    }
}
