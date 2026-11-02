<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\CorrectCriteriaText;
use App\Http\Requests\Criteria\CorrectCriteriaTextRequest;
use App\Http\Resources\Criteria\CriteriaVersionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CorrectCriteriaTextController
{
    public function __invoke(CorrectCriteriaTextRequest $request, CriteriaVersion $version, #[CurrentUser] User $actor, CorrectCriteriaText $correctText): JsonResponse
    {
        $outcome = $correctText->handle($actor, $version, $request->batch());

        return ApiResponse::resource(new CriteriaVersionResource($outcome->version), Response::HTTP_OK, [
            'edit_event_id' => $outcome->editEvent?->id,
            'change_count' => $outcome->changeCount,
            'effect' => $outcome->effect->toArray(),
        ]);
    }
}
