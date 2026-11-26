<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Enums\Identity\RoleKey;
use App\Http\Responses\ApiResponse;
use App\Services\Identity\NationalIdRequirement;
use Illuminate\Http\JsonResponse;

final class RegistrationOptionsController
{
    public function __invoke(NationalIdRequirement $nationalId): JsonResponse
    {
        return ApiResponse::data(['national_id_required' => $nationalId->isRequiredForRoles([RoleKey::Entity])]);
    }
}
