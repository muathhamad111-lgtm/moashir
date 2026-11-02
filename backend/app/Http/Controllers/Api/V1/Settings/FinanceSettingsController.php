<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Settings\UpdateFinanceSettings;
use App\Http\Requests\Settings\UpdateFinanceSettingsRequest;
use App\Http\Resources\Settings\FinanceSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Settings\FinanceSettingsReader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class FinanceSettingsController
{
    public function show(#[CurrentUser] User $viewer, FinanceSettingsReader $reader): JsonResponse
    {
        return ApiResponse::resource(new FinanceSettingsResource($reader->read($viewer)));
    }

    public function update(UpdateFinanceSettingsRequest $request, #[CurrentUser] User $actor, UpdateFinanceSettings $update, FinanceSettingsReader $reader): JsonResponse
    {
        $update->handle($actor, $request->toUpdate());

        return ApiResponse::resource(new FinanceSettingsResource($reader->read($actor)));
    }
}
