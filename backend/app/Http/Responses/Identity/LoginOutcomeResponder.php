<?php

declare(strict_types=1);

namespace App\Http\Responses\Identity;

use App\Actions\Identity\ResolveSessionContext;
use App\Data\Identity\LoginOutcome;
use App\Data\Identity\OtpChallenge;
use App\Enums\Identity\LoginStep;
use App\Http\Resources\Identity\OtpChallengeResource;
use App\Http\Resources\Identity\SessionUserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class LoginOutcomeResponder
{
    public function __construct(private ResolveSessionContext $resolveContext, private Request $request) {}

    /**
     * @param  array<string, mixed>  $extra
     */
    public function respond(LoginOutcome $outcome, array $extra = []): JsonResponse
    {
        return ApiResponse::data([
            'step' => $outcome->step->value,
            ...$this->stepPayload($outcome),
            ...$extra,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stepPayload(LoginOutcome $outcome): array
    {
        return match ($outcome->step) {
            LoginStep::Authenticated => $outcome->user instanceof User ? [
                'user' => (new SessionUserResource($this->resolveContext->handle($outcome->user)))->resolve($this->request),
            ] : [],
            LoginStep::MfaChallenge => ['mfa_required' => true],
            LoginStep::MfaEnrollment => ['mfa_enrollment_required' => true],
            LoginStep::OtpRequired => $outcome->otp instanceof OtpChallenge ? [
                'requires_otp' => true,
                'otp' => (new OtpChallengeResource($outcome->otp))->resolve($this->request),
                'message' => __('messages.otp_sent'),
            ] : [],
        };
    }
}
