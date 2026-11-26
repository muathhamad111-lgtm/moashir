<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kpis;

use App\Actions\Files\UploadPublicImage;
use App\Enums\Files\StoredFilePurpose;
use App\Http\Requests\Files\UploadPublicImageRequest;
use App\Http\Resources\Files\PublicImageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class KpiImageController
{
    public function __invoke(UploadPublicImageRequest $request, #[CurrentUser] User $actor, UploadPublicImage $upload): JsonResponse
    {
        $file = $upload->handle($actor, $request->upload(), StoredFilePurpose::KpiImage);

        return ApiResponse::resource(new PublicImageResource($file), Response::HTTP_CREATED);
    }
}
