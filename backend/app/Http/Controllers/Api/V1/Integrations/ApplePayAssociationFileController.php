<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\RemoveApplePayAssociationFile;
use App\Actions\Integrations\UploadApplePayAssociationFile;
use App\Http\Requests\Integrations\UploadApplePayAssociationFileRequest;
use App\Http\Resources\Integrations\ApplePayAssociationFileResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ApplePayAssociationFileController
{
    public function store(UploadApplePayAssociationFileRequest $request, #[CurrentUser] User $actor, UploadApplePayAssociationFile $upload): JsonResponse
    {
        return ApiResponse::resource(new ApplePayAssociationFileResource($upload->handle($actor, $request->upload())), Response::HTTP_CREATED);
    }

    public function destroy(RemoveApplePayAssociationFile $remove): Response
    {
        $remove->handle();

        return ApiResponse::noContent();
    }
}
