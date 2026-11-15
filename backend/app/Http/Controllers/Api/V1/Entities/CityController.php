<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ListCities;
use App\Actions\Entities\ToggleCity;
use App\Http\Requests\Entities\IndexCitiesRequest;
use App\Http\Requests\Entities\ToggleCityRequest;
use App\Http\Resources\Entities\CityResource;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\City;
use Illuminate\Http\JsonResponse;

final class CityController
{
    public function index(IndexCitiesRequest $request, ListCities $listCities): JsonResponse
    {
        return ApiResponse::paginated($listCities->handle($request->filters()), CityResource::class);
    }

    public function toggle(ToggleCityRequest $request, City $city, ToggleCity $toggleCity): JsonResponse
    {
        return ApiResponse::resource(new CityResource($toggleCity->handle($city, $request->isActive())));
    }
}
