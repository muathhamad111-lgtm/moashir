<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\CreateDraftVersion;
use App\Actions\Criteria\DiscardDraftVersion;
use App\Actions\Criteria\ListCriteriaVersions;
use App\Actions\Criteria\ShowCriteriaVersion;
use App\Http\Requests\Criteria\IndexCriteriaVersionsRequest;
use App\Http\Requests\Criteria\StoreCriteriaVersionRequest;
use App\Http\Resources\Criteria\CriteriaVersionResource;
use App\Http\Resources\Criteria\CriteriaVersionSummaryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CriteriaVersionController
{
    public function index(IndexCriteriaVersionsRequest $request, ListCriteriaVersions $listVersions): JsonResponse
    {
        return ApiResponse::paginated($listVersions->handle($request->status(), $request->perPage()), CriteriaVersionSummaryResource::class);
    }

    public function store(StoreCriteriaVersionRequest $request, #[CurrentUser] User $actor, CreateDraftVersion $createDraft): JsonResponse
    {
        $draft = $createDraft->handle($actor, $request->cloneFromVersionId(), $request->label());

        return ApiResponse::resource(new CriteriaVersionResource($draft), Response::HTTP_CREATED);
    }

    public function show(CriteriaVersion $version, ShowCriteriaVersion $showVersion): JsonResponse
    {
        return ApiResponse::resource(new CriteriaVersionSummaryResource($showVersion->handle($version)));
    }

    public function destroy(CriteriaVersion $version, #[CurrentUser] User $actor, DiscardDraftVersion $discardDraft): Response
    {
        $discardDraft->handle($actor, $version);

        return ApiResponse::noContent();
    }
}
