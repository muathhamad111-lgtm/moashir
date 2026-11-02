<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\ListTaqnyatSenders;
use App\Data\Integrations\SmsSender;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class TaqnyatSenderController
{
    public function __invoke(ListTaqnyatSenders $senders): JsonResponse
    {
        return ApiResponse::data(array_map(static fn (SmsSender $sender): array => $sender->toArray(), $senders->handle()));
    }
}
