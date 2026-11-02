<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\CheckApplePayAssociationFile;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CheckApplePayAssociationFileController
{
    public function __invoke(CheckApplePayAssociationFile $check): JsonResponse
    {
        return ApiResponse::data($check->handle());
    }
}
