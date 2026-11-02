<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\MfaEnrollment;
use App\Exceptions\Identity\MfaRejected;
use App\Models\Identity\User;
use App\Support\ScalarValue;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;

final readonly class StartMfaEnrollment
{
    public function __construct(private EnableTwoFactorAuthentication $enable) {}

    public function handle(User $user): MfaEnrollment
    {
        if ($user->hasConfirmedTotp()) {
            throw MfaRejected::alreadyEnrolled();
        }

        ($this->enable)($user, true);

        return new MfaEnrollment(
            $user->twoFactorQrCodeSvg(),
            ScalarValue::string(Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret)),
        );
    }
}
