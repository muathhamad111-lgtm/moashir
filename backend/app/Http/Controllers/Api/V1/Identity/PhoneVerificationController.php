<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ConfirmPhoneVerification;
use App\Actions\Identity\StartPhoneVerification;
use App\Http\Requests\Identity\ConfirmPhoneVerificationRequest;
use App\Http\Resources\Identity\OtpChallengeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class PhoneVerificationController
{
    public function store(#[CurrentUser] User $user, StartPhoneVerification $start): JsonResponse
    {
        return ApiResponse::resource(new OtpChallengeResource($start->handle($user)));
    }

    public function confirm(ConfirmPhoneVerificationRequest $request, #[CurrentUser] User $user, ConfirmPhoneVerification $confirm): JsonResponse
    {
        $verified = $confirm->handle($user, $request->challengeId(), $request->code());

        return ApiResponse::data(['phone_verified' => true, 'phone_verified_at' => $verified->phone_verified_at?->toIso8601String()]);
    }
}
