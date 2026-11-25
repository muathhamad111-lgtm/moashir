<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\IssueEntityDocumentDownload;
use App\Enums\Entities\EntityDocument;
use App\Http\Resources\Files\SignedDownloadResource;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class EntityDocumentDownloadController
{
    public function __invoke(Entity $entity, EntityDocument $document, #[CurrentUser] User $viewer, IssueEntityDocumentDownload $issueDownload): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($issueDownload->handle($viewer, $entity, $document)));
    }
}
