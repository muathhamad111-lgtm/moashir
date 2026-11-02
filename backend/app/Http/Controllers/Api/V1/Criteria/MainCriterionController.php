<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\CreateMainCriterion;
use App\Actions\Criteria\ListMainCriteria;
use App\Actions\Criteria\RemoveMainCriterion;
use App\Actions\Criteria\UpdateMainCriterion;
use App\Http\Requests\Criteria\IndexMainCriteriaRequest;
use App\Http\Requests\Criteria\StoreMainCriterionRequest;
use App\Http\Requests\Criteria\UpdateMainCriterionRequest;
use App\Http\Resources\Criteria\MainCriterionResource;
use App\Http\Resources\Criteria\SubCriterionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class MainCriterionController
{
    public function index(IndexMainCriteriaRequest $request, ListMainCriteria $listMains): JsonResponse
    {
        $page = $listMains->handle($request->listQuery(), app()->getLocale());

        return ApiResponse::paginated($page->mains, MainCriterionResource::class, [
            'version' => $page->version instanceof CriteriaVersion ? [
                'id' => $page->version->id,
                'version_number' => $page->version->version_number,
                'status' => $page->version->status->value,
                'label' => $page->version->label?->toArray(),
            ] : null,
        ]);
    }

    public function store(StoreMainCriterionRequest $request, #[CurrentUser] User $actor, CreateMainCriterion $createMain, CriteriaTreeLoader $loader): JsonResponse
    {
        $main = $createMain->handle($actor, $request->attributesData(), $request->isActive());

        return ApiResponse::resource(new MainCriterionResource($loader->loadMain($main)), Response::HTTP_CREATED);
    }

    public function show(MainCriterion $main, CriteriaTreeLoader $loader): JsonResponse
    {
        $loaded = $loader->loadMain($main);

        return ApiResponse::data([
            'main' => new MainCriterionResource($loaded)->resolve(request()),
            'subs' => SubCriterionResource::collection($loaded->subCriteria)->resolve(request()),
        ]);
    }

    public function update(UpdateMainCriterionRequest $request, MainCriterion $main, #[CurrentUser] User $actor, UpdateMainCriterion $updateMain, CriteriaTreeLoader $loader): JsonResponse
    {
        $updated = $updateMain->handle($actor, $main, $request->attributesData($main));

        return ApiResponse::resource(new MainCriterionResource($loader->loadMain($updated)));
    }

    public function destroy(MainCriterion $main, #[CurrentUser] User $actor, RemoveMainCriterion $removeMain): Response
    {
        $removeMain->handle($actor, $main);

        return ApiResponse::noContent();
    }
}
