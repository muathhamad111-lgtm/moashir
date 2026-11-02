<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\StartMfaEnrollment;
use App\Http\Resources\Identity\MfaEnrollmentResource;
use App\Http\Responses\ApiResponse;
use App\Services\Identity\MfaSubject;
use Illuminate\Http\JsonResponse;

final class StartMfaEnrollmentController
{
    public function __invoke(MfaSubject $subject, StartMfaEnrollment $startEnrollment): JsonResponse
    {
        return ApiResponse::resource(new MfaEnrollmentResource($startEnrollment->handle($subject->resolve())));
    }
}
