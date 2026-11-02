<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ResendEntityVerification;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ResendEntityVerificationController
{
    public function __invoke(Entity $entity, ResendEntityVerification $resendVerification): JsonResponse
    {
        $updated = $resendVerification->handle($entity);

        return ApiResponse::data([
            'verification_reminded_at' => $updated->verification_reminded_at?->toIso8601String(),
        ], Response::HTTP_ACCEPTED);
    }
}
