<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Nda;

use App\Actions\Nda\ListUserNdaAcceptances;
use App\Http\Requests\Nda\PageNdaRequest;
use App\Http\Resources\Nda\NdaAcceptanceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Http\JsonResponse;

/**
 * The read-only agreement history on the staff page.
 */
final class UserNdaAcceptanceController
{
    public function __invoke(PageNdaRequest $request, User $managedUser, ListUserNdaAcceptances $list): JsonResponse
    {
        return ApiResponse::paginated($list->handle($managedUser, $request->perPage()), NdaAcceptanceResource::class);
    }
}
