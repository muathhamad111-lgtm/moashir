<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\CreateCity;
use App\Actions\Entities\ListCities;
use App\Actions\Entities\ToggleCity;
use App\Actions\Entities\UpdateCity;
use App\Http\Requests\Entities\IndexCitiesRequest;
use App\Http\Requests\Entities\StoreCityRequest;
use App\Http\Requests\Entities\ToggleCityRequest;
use App\Http\Requests\Entities\UpdateCityRequest;
use App\Http\Resources\Entities\CityResource;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\City;
use App\Models\Entities\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CityController
{
    public function index(IndexCitiesRequest $request, ListCities $listCities): JsonResponse
    {
        return ApiResponse::paginated($listCities->handle($request->filters()), CityResource::class);
    }

    public function store(StoreCityRequest $request, Region $region, CreateCity $createCity): JsonResponse
    {
        return ApiResponse::resource(new CityResource($createCity->handle($region, $request->city())), Response::HTTP_CREATED);
    }

    public function update(UpdateCityRequest $request, City $city, UpdateCity $updateCity): JsonResponse
    {
        return ApiResponse::resource(new CityResource($updateCity->handle($city, $request->city())));
    }

    public function toggle(ToggleCityRequest $request, City $city, ToggleCity $toggleCity): JsonResponse
    {
        return ApiResponse::resource(new CityResource($toggleCity->handle($city, $request->isActive())));
    }
}
