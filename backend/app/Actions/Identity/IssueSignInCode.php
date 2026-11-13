<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\OtpChallenge;
use App\Data\Identity\OtpDelivery;
use App\Services\Identity\SignInSmsCap;

final readonly class IssueSignInCode
{
    public function __construct(private IssueOtpChallenge $issueChallenge, private SignInSmsCap $smsCap) {}

    public function handle(OtpDelivery $delivery): OtpChallenge
    {
        $challenge = $this->issueChallenge->handle($delivery);
        $this->smsCap->recordSent($challenge);

        return $challenge;
    }
}
