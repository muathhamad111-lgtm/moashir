<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Nda;

use App\Actions\Nda\AcceptNda;
use App\Http\Requests\Nda\AcceptNdaRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Nda\NdaAcceptanceCheck;
use App\Services\Nda\NdaTextPresenter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * The agreement the signed-in user must accept, and the acceptance itself. Both stay reachable while the gate
 * refuses every other signed-in route.
 */
final class MyNdaController
{
    public function show(#[CurrentUser] User $user, NdaAcceptanceCheck $check, NdaTextPresenter $presenter): JsonResponse
    {
        $current = $check->current();

        return ApiResponse::data([
            'acceptance_required' => $check->isRequiredFor($user),
            'version' => $current instanceof TermsVersion ? $presenter->present($current) : null,
        ]);
    }

    public function store(AcceptNdaRequest $request, #[CurrentUser] User $user, AcceptNda $accept): JsonResponse
    {
        $version = $accept->handle($user, $request->termsVersionId());

        return ApiResponse::data(['accepted' => true, 'terms_version_id' => $version->id]);
    }
}
