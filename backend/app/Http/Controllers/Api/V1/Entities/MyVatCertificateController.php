<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\IssueVatCertificateDownload;
use App\Actions\Entities\RemoveOwnVatCertificate;
use App\Actions\Entities\UploadOwnVatCertificate;
use App\Http\Requests\Entities\UploadVatCertificateRequest;
use App\Http\Resources\Entities\OwnEntityResource;
use App\Http\Resources\Files\SignedDownloadResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Entities\OwnEntity;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class MyVatCertificateController
{
    public function store(UploadVatCertificateRequest $request, #[CurrentUser] User $owner, UploadOwnVatCertificate $upload): JsonResponse
    {
        $entity = $upload->handle($owner, $request->upload());

        return ApiResponse::resource(new OwnEntityResource($entity->load(OwnEntityResource::RELATIONS)), Response::HTTP_ACCEPTED);
    }

    public function destroy(#[CurrentUser] User $owner, RemoveOwnVatCertificate $remove): JsonResponse
    {
        $entity = $remove->handle($owner);

        return ApiResponse::resource(new OwnEntityResource($entity->load(OwnEntityResource::RELATIONS)), Response::HTTP_ACCEPTED);
    }

    public function download(#[CurrentUser] User $owner, OwnEntity $ownEntity, IssueVatCertificateDownload $issueDownload): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($issueDownload->handle($owner, $ownEntity->of($owner))));
    }
}
