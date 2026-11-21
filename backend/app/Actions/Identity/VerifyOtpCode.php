<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\OtpPurpose;
use App\Enums\Identity\OtpVerification;
use App\Exceptions\Identity\OtpRejected;
use App\Models\Identity\OtpCode;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\AuthSettings;
use App\Services\Identity\DecoyOtpChallenges;
use App\Services\Identity\OtpAttemptEvaluator;
use Illuminate\Support\Facades\DB;

final readonly class VerifyOtpCode
{
    public function __construct(
        private AuthSettings $settings,
        private OtpAttemptEvaluator $evaluator,
        private DecoyOtpChallenges $decoys,
        private AuthenticationEventRecorder $events,
    ) {}

    public function handle(string $challengeId, string $code, OtpPurpose $purpose): OtpCode
    {
        $otp = OtpCode::query()->where('challenge_id', $challengeId)->where('purpose', $purpose)->first();

        if ($otp === null) {
            $this->rejectWithoutChallenge($challengeId);
        }

        $result = DB::transaction(fn (): OtpVerification => $this->evaluator->evaluate(
            OtpCode::query()->whereKey($otp->id)->lockForUpdate()->firstOrFail(),
            $code,
        ));

        if ($result !== OtpVerification::Verified) {
            $this->events->forUser(AuthEventType::OtpFailed, $otp->user, ['purpose' => $purpose->value]);

            throw OtpRejected::fromVerification($result);
        }

        $this->events->forUser(AuthEventType::OtpVerified, $otp->user, ['purpose' => $purpose->value]);

        return $otp->refresh();
    }

    private function rejectWithoutChallenge(string $challengeId): never
    {
        if (! $this->decoys->isDecoy($challengeId)) {
            throw OtpRejected::challengeInvalid();
        }

        $attempts = $this->decoys->recordAttempt($challengeId);

        throw OtpRejected::fromVerification($attempts >= $this->settings->otpMaxAttempts() ? OtpVerification::AttemptsExceeded : OtpVerification::InvalidCode);
    }
}
