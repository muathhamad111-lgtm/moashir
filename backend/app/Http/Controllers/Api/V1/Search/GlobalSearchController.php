<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Search;

use App\Actions\Search\RunGlobalSearch;
use App\Http\Requests\Search\GlobalSearchRequest;
use App\Http\Resources\Search\SearchResultGroupResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class GlobalSearchController
{
    public function __invoke(GlobalSearchRequest $request, #[CurrentUser] User $actor, RunGlobalSearch $search): JsonResponse
    {
        $query = $request->searchQuery();
        $groups = SearchResultGroupResource::collection($search->handle($actor, $query))->resolve($request);

        return ApiResponse::data($groups, meta: ['limit' => $query->limit]);
    }
}
