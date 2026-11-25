<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\IssueEntityDocumentDownload;
use App\Actions\Entities\UploadOwnEntityDocument;
use App\Enums\Entities\EntityDocument;
use App\Http\Requests\Entities\UploadEntityDocumentRequest;
use App\Http\Resources\Entities\OwnEntityResource;
use App\Http\Resources\Files\SignedDownloadResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Entities\OwnEntity;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class MyEntityDocumentController
{
    public function store(UploadEntityDocumentRequest $request, EntityDocument $document, #[CurrentUser] User $owner, UploadOwnEntityDocument $upload): JsonResponse
    {
        $entity = $upload->handle($owner, $document, $request->upload());

        return ApiResponse::resource(new OwnEntityResource($entity->load(OwnEntityResource::RELATIONS)), Response::HTTP_ACCEPTED);
    }

    public function download(EntityDocument $document, #[CurrentUser] User $owner, OwnEntity $ownEntity, IssueEntityDocumentDownload $issueDownload): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($issueDownload->handle($owner, $ownEntity->of($owner), $document)));
    }
}
