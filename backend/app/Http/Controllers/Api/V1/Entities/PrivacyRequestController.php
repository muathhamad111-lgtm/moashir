<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\CreatePrivacyRequest;
use App\Actions\Entities\FulfilPrivacyRequest;
use App\Actions\Entities\ListPrivacyRequests;
use App\Actions\Entities\RejectPrivacyRequest;
use App\Http\Requests\Entities\IndexPrivacyRequestsRequest;
use App\Http\Requests\Entities\StorePrivacyRequestRequest;
use App\Http\Requests\Identity\OptionalReasonRequest;
use App\Http\Requests\Identity\RequiredReasonRequest;
use App\Http\Resources\Entities\PrivacyRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\PrivacyRequest;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class PrivacyRequestController
{
    private const array RELATIONS = ['entity', 'createdBy', 'decidedBy'];

    public function index(IndexPrivacyRequestsRequest $request, ListPrivacyRequests $listRequests): JsonResponse
    {
        return ApiResponse::paginated($listRequests->handle($request->filters()), PrivacyRequestResource::class);
    }

    public function store(StorePrivacyRequestRequest $request, #[CurrentUser] User $actor, CreatePrivacyRequest $createRequest): JsonResponse
    {
        $created = $createRequest->handle($actor, $request->privacyRequest());

        return ApiResponse::resource(new PrivacyRequestResource($created->load(self::RELATIONS)), Response::HTTP_CREATED);
    }

    public function show(PrivacyRequest $privacyRequest): JsonResponse
    {
        return ApiResponse::resource(new PrivacyRequestResource($privacyRequest->load(self::RELATIONS)));
    }

    public function fulfil(OptionalReasonRequest $request, PrivacyRequest $privacyRequest, #[CurrentUser] User $actor, FulfilPrivacyRequest $fulfil): JsonResponse
    {
        return ApiResponse::resource(new PrivacyRequestResource($fulfil->handle($actor, $privacyRequest, $request->reason())->load(self::RELATIONS)));
    }

    public function reject(RequiredReasonRequest $request, PrivacyRequest $privacyRequest, #[CurrentUser] User $actor, RejectPrivacyRequest $reject): JsonResponse
    {
        return ApiResponse::resource(new PrivacyRequestResource($reject->handle($actor, $privacyRequest, $request->reason())->load(self::RELATIONS)));
    }
}
