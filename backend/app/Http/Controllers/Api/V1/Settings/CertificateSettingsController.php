<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Settings\UpdateCertificateSettings;
use App\Http\Requests\Settings\UpdateCertificateSettingsRequest;
use App\Http\Resources\Certificates\CertificateSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Certificates\CertificateSettingsReader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class CertificateSettingsController
{
    public function show(CertificateSettingsReader $reader): JsonResponse
    {
        return ApiResponse::resource(new CertificateSettingsResource($reader->read()));
    }

    public function update(UpdateCertificateSettingsRequest $request, #[CurrentUser] User $actor, UpdateCertificateSettings $update, CertificateSettingsReader $reader): JsonResponse
    {
        $update->handle($actor, $request->toUpdate());

        return ApiResponse::resource(new CertificateSettingsResource($reader->read()));
    }
}
