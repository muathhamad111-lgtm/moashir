<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Nda;

use App\Actions\Nda\ListUserNdaAcceptances;
use App\Http\Requests\Nda\PageNdaRequest;
use App\Http\Resources\Nda\NdaAcceptanceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Nda\NdaTextPresenter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class MyNdaAcceptanceController
{
    public function index(PageNdaRequest $request, #[CurrentUser] User $user, ListUserNdaAcceptances $list): JsonResponse
    {
        return ApiResponse::paginated($list->handle($user, $request->perPage()), NdaAcceptanceResource::class);
    }

    /**
     * The text of a version the user accepted (view only).
     */
    public function show(TermsVersion $ndaVersion, NdaTextPresenter $presenter): JsonResponse
    {
        return ApiResponse::data($presenter->present($ndaVersion));
    }
}
