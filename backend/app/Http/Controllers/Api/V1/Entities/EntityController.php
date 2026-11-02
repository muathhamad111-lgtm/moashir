<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ListEntities;
use App\Actions\Entities\SummarizeEntities;
use App\Actions\Entities\UpdateEntityOnBehalf;
use App\Http\Requests\Entities\IndexEntitiesRequest;
use App\Http\Requests\Entities\UpdateEntityRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\EntityRecordScope;
use App\Support\Entities\EntityResourceVariant;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class EntityController
{
    public function __construct(private EntityRecordScope $scope) {}

    public function index(IndexEntitiesRequest $request, #[CurrentUser] User $actor, ListEntities $listEntities, SummarizeEntities $summarize): JsonResponse
    {
        $filters = $request->filters();

        return ApiResponse::paginated(
            $listEntities->handle($actor, $filters),
            EntityResourceVariant::classFor($this->scope->accessOf($actor)),
            ['stats' => $summarize->handle($actor, $filters)],
        );
    }

    public function show(Entity $entity, #[CurrentUser] User $actor): JsonResponse
    {
        return ApiResponse::resource(EntityResourceVariant::of($entity, $this->scope->accessOf($actor)));
    }

    public function update(UpdateEntityRequest $request, Entity $entity, #[CurrentUser] User $actor, UpdateEntityOnBehalf $updateEntity): JsonResponse
    {
        $updated = $updateEntity->handle($actor, $entity, $request->profile(), $request->contact(), $request->reason());

        return ApiResponse::resource(EntityResourceVariant::of($updated, $this->scope->accessOf($actor)));
    }
}
