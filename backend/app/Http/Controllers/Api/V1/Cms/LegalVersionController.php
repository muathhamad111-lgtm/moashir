<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cms;

use App\Actions\Cms\CreateLegalDraft;
use App\Actions\Cms\DiscardLegalDraft;
use App\Actions\Cms\ListLegalVersions;
use App\Actions\Cms\UpdateLegalDraft;
use App\Enums\Identity\TermsKind;
use App\Http\Requests\Cms\IndexLegalVersionsRequest;
use App\Http\Requests\Cms\StoreLegalVersionRequest;
use App\Http\Requests\Cms\UpdateLegalVersionRequest;
use App\Http\Resources\Cms\LegalVersionResource;
use App\Http\Resources\Cms\LegalVersionSummaryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\TermsVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class LegalVersionController
{
    public function index(IndexLegalVersionsRequest $request, TermsKind $kind, ListLegalVersions $list): JsonResponse
    {
        return ApiResponse::paginated($list->handle($kind, $request->perPage()), LegalVersionSummaryResource::class);
    }

    public function store(StoreLegalVersionRequest $request, TermsKind $kind, CreateLegalDraft $create): JsonResponse
    {
        return ApiResponse::resource(new LegalVersionResource($create->handle($request->draft($kind))), Response::HTTP_CREATED);
    }

    public function show(TermsVersion $termsVersion): JsonResponse
    {
        return ApiResponse::resource(new LegalVersionResource($termsVersion));
    }

    public function update(UpdateLegalVersionRequest $request, TermsVersion $termsVersion, UpdateLegalDraft $update): JsonResponse
    {
        return ApiResponse::resource(new LegalVersionResource($update->handle($termsVersion, $request->changes())));
    }

    public function destroy(TermsVersion $termsVersion, DiscardLegalDraft $discard): Response
    {
        $discard->handle($termsVersion);

        return ApiResponse::noContent();
    }
}
