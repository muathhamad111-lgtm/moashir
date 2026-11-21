<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\RemindPaidDraft;
use App\Http\Resources\Assessments\PaidDraftReminderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use Illuminate\Http\JsonResponse;

final class RemindRequestController
{
    public function __invoke(Assessment $assessment, RemindPaidDraft $remind): JsonResponse
    {
        return ApiResponse::resource(new PaidDraftReminderResource($remind->handle($assessment)));
    }
}
