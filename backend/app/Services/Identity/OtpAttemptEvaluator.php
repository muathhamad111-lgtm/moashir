<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Contracts\Identity\OneTimeCode;
use App\Enums\Identity\OtpVerification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

final readonly class OtpAttemptEvaluator
{
    public function __construct(private AuthSettings $settings, private SecretHasher $hasher) {}

    /**
     * Checks one attempt against a code the caller has locked for update, consuming it on a match and
     * counting (then voiding at the limit) a wrong one.
     */
    public function evaluate(Model&OneTimeCode $locked, string $code): OtpVerification
    {
        $precondition = $this->precondition($locked);

        if ($precondition instanceof OtpVerification) {
            return $precondition;
        }

        if ($this->hasher->matches($code, $locked->codeHash())) {
            $locked->forceFill(['consumed_at' => Date::now()])->save();

            return OtpVerification::Verified;
        }

        $attempts = $locked->failedAttempts() + 1;
        $locked->forceFill(['attempts' => $attempts])->save();

        if ($attempts >= $this->settings->otpMaxAttempts()) {
            $locked->forceFill(['voided_at' => Date::now()])->save();

            return OtpVerification::AttemptsExceeded;
        }

        return OtpVerification::InvalidCode;
    }

    private function precondition(OneTimeCode $code): ?OtpVerification
    {
        return match (true) {
            ! $code->isOpen() => OtpVerification::Closed,
            $code->isExpired() => OtpVerification::Expired,
            $code->failedAttempts() >= $this->settings->otpMaxAttempts() => OtpVerification::AttemptsExceeded,
            default => null,
        };
    }
}
