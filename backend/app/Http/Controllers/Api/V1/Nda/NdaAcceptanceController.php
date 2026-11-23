<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Nda;

use App\Actions\Nda\ListNdaAcceptances;
use App\Http\Requests\Nda\IndexNdaAcceptancesRequest;
use App\Http\Resources\Nda\NdaVersionSummaryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\TermsVersion;
use App\Services\Nda\NdaRegisterPresenter;
use Illuminate\Http\JsonResponse;

final class NdaAcceptanceController
{
    public function index(IndexNdaAcceptancesRequest $request, ListNdaAcceptances $list, NdaRegisterPresenter $presenter): JsonResponse
    {
        $page = $list->handle($request->filter(), $request->perPage());

        return ApiResponse::page($page->users, $presenter->rows($page), [
            'summary' => $page->summary->toArray(),
            'version' => $page->version instanceof TermsVersion ? new NdaVersionSummaryResource($page->version)->resolve($request) : null,
        ]);
    }
}
