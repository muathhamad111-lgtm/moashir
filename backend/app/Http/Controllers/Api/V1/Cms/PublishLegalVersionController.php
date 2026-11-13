<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cms;

use App\Actions\Cms\PublishLegalDraft;
use App\Http\Requests\Cms\PublishLegalVersionRequest;
use App\Http\Resources\Cms\LegalVersionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class PublishLegalVersionController
{
    public function __invoke(PublishLegalVersionRequest $request, TermsVersion $termsVersion, #[CurrentUser] User $actor, PublishLegalDraft $publish): JsonResponse
    {
        return ApiResponse::resource(new LegalVersionResource($publish->handle($actor, $termsVersion, $request->reason())));
    }
}
