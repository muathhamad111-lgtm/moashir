<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\EmailVerificationToken;
use App\Enums\Identity\OtpVerification;
use App\Exceptions\Identity\OtpRejected;
use App\Models\Identity\RegistrationEmailVerification;
use App\Services\Entities\RegistrationEmailTokens;
use App\Services\Identity\OtpAttemptEvaluator;
use Illuminate\Support\Facades\DB;

final readonly class VerifyRegistrationEmailCode
{
    public function __construct(private OtpAttemptEvaluator $evaluator, private RegistrationEmailTokens $tokens) {}

    public function handle(string $challengeId, string $code): EmailVerificationToken
    {
        $verification = RegistrationEmailVerification::query()->where('challenge_id', $challengeId)->first();

        if (! $verification instanceof RegistrationEmailVerification) {
            throw OtpRejected::challengeInvalid();
        }

        $result = DB::transaction(fn (): OtpVerification => $this->evaluator->evaluate(
            RegistrationEmailVerification::query()->whereKey($verification->id)->lockForUpdate()->firstOrFail(),
            $code,
        ));

        if ($result !== OtpVerification::Verified) {
            throw OtpRejected::fromVerification($result);
        }

        return $this->tokens->issue($verification);
    }
}
