<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Nda;

use App\Actions\Cms\CreateLegalDraft;
use App\Actions\Cms\DiscardLegalDraft;
use App\Actions\Cms\UpdateLegalDraft;
use App\Enums\Identity\TermsKind;
use App\Http\Requests\Cms\StoreLegalVersionRequest;
use App\Http\Requests\Cms\UpdateLegalVersionRequest;
use App\Http\Resources\Nda\NdaVersionResource;
use App\Http\Responses\ApiResponse;
use App\Services\Nda\NdaDraftFinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The single agreement draft, edited with the same rules (one draft, ar and en text, no markup) as the public
 * legal texts.
 */
final class NdaDraftController
{
    public function store(StoreLegalVersionRequest $request, CreateLegalDraft $create): JsonResponse
    {
        return ApiResponse::resource(new NdaVersionResource($create->handle($request->draft(TermsKind::Nda))), Response::HTTP_CREATED);
    }

    public function update(UpdateLegalVersionRequest $request, NdaDraftFinder $drafts, UpdateLegalDraft $update): JsonResponse
    {
        return ApiResponse::resource(new NdaVersionResource($update->handle($drafts->findOrFail(), $request->changes())));
    }

    public function destroy(NdaDraftFinder $drafts, DiscardLegalDraft $discard): Response
    {
        $discard->handle($drafts->findOrFail());

        return ApiResponse::noContent();
    }
}
