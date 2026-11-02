<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Enums\Identity\TermsKind;
use App\Http\Resources\Identity\TermsVersionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\TermsVersion;
use App\Services\Identity\CurrentTermsVersions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CurrentTermsController
{
    public function __invoke(Request $request, CurrentTermsVersions $terms): JsonResponse
    {
        $current = [];

        foreach (TermsKind::cases() as $kind) {
            $version = $terms->of($kind);
            $current[$kind->value] = $version instanceof TermsVersion ? new TermsVersionResource($version)->resolve($request) : null;
        }

        return ApiResponse::data($current);
    }
}
