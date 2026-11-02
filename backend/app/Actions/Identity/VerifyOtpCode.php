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
use App\Services\Identity\SecretHasher;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class VerifyOtpCode
{
    public function __construct(
        private AuthSettings $settings,
        private SecretHasher $hasher,
        private DecoyOtpChallenges $decoys,
        private AuthenticationEventRecorder $events,
    ) {}

    public function handle(string $challengeId, string $code, OtpPurpose $purpose): OtpCode
    {
        $otp = OtpCode::query()->where('challenge_id', $challengeId)->where('purpose', $purpose)->first();

        if ($otp === null) {
            $this->rejectWithoutChallenge($challengeId);
        }

        $result = DB::transaction(fn (): OtpVerification => $this->evaluate($otp, $code));

        if ($result !== OtpVerification::Verified) {
            $this->events->forUser(AuthEventType::OtpFailed, $otp->user, ['purpose' => $purpose->value]);
            $this->reject($result);
        }

        $this->events->forUser(AuthEventType::OtpVerified, $otp->user, ['purpose' => $purpose->value]);

        return $otp->refresh();
    }

    private function evaluate(OtpCode $otp, string $code): OtpVerification
    {
        $locked = OtpCode::query()->whereKey($otp->id)->lockForUpdate()->firstOrFail();

        $precondition = $this->precondition($locked);

        if ($precondition instanceof OtpVerification) {
            return $precondition;
        }

        if ($this->hasher->matches($code, $locked->code_hash)) {
            $locked->forceFill(['consumed_at' => Date::now()])->save();

            return OtpVerification::Verified;
        }

        $locked->forceFill(['attempts' => $locked->attempts + 1])->save();

        if ($locked->attempts >= $this->settings->otpMaxAttempts()) {
            $locked->forceFill(['voided_at' => Date::now()])->save();

            return OtpVerification::AttemptsExceeded;
        }

        return OtpVerification::InvalidCode;
    }

    private function precondition(OtpCode $otp): ?OtpVerification
    {
        return match (true) {
            ! $otp->isOpen() => OtpVerification::Closed,
            $otp->isExpired() => OtpVerification::Expired,
            $otp->attempts >= $this->settings->otpMaxAttempts() => OtpVerification::AttemptsExceeded,
            default => null,
        };
    }

    private function rejectWithoutChallenge(string $challengeId): never
    {
        if (! $this->decoys->isDecoy($challengeId)) {
            throw OtpRejected::challengeInvalid();
        }

        $attempts = $this->decoys->recordAttempt($challengeId);

        $this->reject($attempts >= $this->settings->otpMaxAttempts() ? OtpVerification::AttemptsExceeded : OtpVerification::InvalidCode);
    }

    private function reject(OtpVerification $result): never
    {
        throw match ($result) {
            OtpVerification::Expired => OtpRejected::expired(),
            OtpVerification::AttemptsExceeded => OtpRejected::attemptsExceeded(),
            OtpVerification::InvalidCode => OtpRejected::invalidCode(),
            default => OtpRejected::challengeInvalid(),
        };
    }
}
