<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\Identity\User;
use App\Support\ScalarValue;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

final readonly class TotpVerifier
{
    public function __construct(private TwoFactorAuthenticationProvider $provider) {}

    public function verifyCode(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        return $this->provider->verify(ScalarValue::string(Fortify::currentEncrypter()->decrypt($user->two_factor_secret)), $code);
    }

    public function consumeRecoveryCode(User $user, string $recoveryCode): bool
    {
        if ($user->two_factor_recovery_codes === null) {
            return false;
        }

        $codes = $user->recoveryCodes();

        if (! is_array($codes) || ! in_array($recoveryCode, $codes, true)) {
            return false;
        }

        $user->replaceRecoveryCode($recoveryCode);

        return true;
    }
}
