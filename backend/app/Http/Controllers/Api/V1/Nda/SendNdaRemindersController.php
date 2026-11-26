<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Nda;

use App\Actions\Nda\SendNdaReminders;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class SendNdaRemindersController
{
    public function __invoke(SendNdaReminders $send): JsonResponse
    {
        $reminder = $send->handle();

        return ApiResponse::data([
            'recipients' => $reminder->recipients,
            'sent_at' => $reminder->created_at->toIso8601String(),
            'next_manual_reminder_at' => $reminder->created_at->copy()->addHours(SendNdaReminders::MANUAL_INTERVAL_HOURS)->toIso8601String(),
        ], Response::HTTP_ACCEPTED);
    }
}
