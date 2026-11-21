<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Requests\Identity\CheckPasswordResetLinkRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Identity\UsablePasswordResetLinkFinder;
use Illuminate\Http\JsonResponse;

/**
 * Tells the reset page whether its link still works. Reading it neither uses nor extends the link.
 */
final class CheckPasswordResetLinkController
{
    public function __invoke(CheckPasswordResetLinkRequest $request, UsablePasswordResetLinkFinder $links): JsonResponse
    {
        $links->find($request->token());

        return ApiResponse::data(['usable' => true]);
    }
}
