<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cms;

use App\Actions\Cms\GetPublicHome;
use App\Actions\Cms\GetPublicLanding;
use App\Actions\Cms\GetPublicLegalText;
use App\Actions\Cms\GetPublicPage;
use App\Actions\Cms\GetPublicSiteStatus;
use App\Enums\Cms\CmsPageKey;
use App\Enums\Identity\TermsKind;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PublicContentController
{
    public function status(GetPublicSiteStatus $status): JsonResponse
    {
        return ApiResponse::data($status->handle());
    }

    public function home(GetPublicHome $home): JsonResponse
    {
        return ApiResponse::data($home->handle());
    }

    public function landing(GetPublicLanding $landing): JsonResponse
    {
        return ApiResponse::data($landing->handle());
    }

    public function page(CmsPageKey $page, GetPublicPage $read): JsonResponse
    {
        return ApiResponse::data($read->handle($page));
    }

    public function legal(TermsKind $kind, GetPublicLegalText $read): JsonResponse
    {
        return ApiResponse::data($read->handle($kind));
    }
}
