<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\SessionGroup;
use Illuminate\Contracts\Config\Repository;

final readonly class AuthSettings
{
    public function __construct(private Repository $config) {}

    public function otpLength(): int
    {
        return $this->number('otp.length');
    }

    public function otpExpirySeconds(): int
    {
        return $this->number('otp.expiry_seconds');
    }

    public function otpResendCooldownSeconds(): int
    {
        return $this->number('otp.resend_cooldown_seconds');
    }

    public function otpMaxAttempts(): int
    {
        return $this->number('otp.max_attempts');
    }

    public function otpPerUserPerTenMinutes(): int
    {
        return max(1, $this->number('otp.per_user_per_ten_minutes'));
    }

    public function otpPerUserPerDay(): int
    {
        return max(1, $this->number('otp.per_user_per_day'));
    }

    public function signInSmsPerHour(): int
    {
        return max(1, $this->number('otp.sign_in_sms_per_hour'));
    }

    public function lockoutMaxAttempts(): int
    {
        return $this->number('lockout.max_attempts');
    }

    public function lockoutMinutes(): int
    {
        return $this->number('lockout.minutes');
    }

    public function passwordHistoryDepth(): int
    {
        return $this->number('password.history');
    }

    public function invitationTtlHours(): int
    {
        return $this->number('invitation_ttl_hours');
    }

    public function pendingAuthenticationTtlMinutes(): int
    {
        return $this->number('pending_authentication_ttl_minutes');
    }

    public function idleMinutes(SessionGroup $group): int
    {
        return $this->number('session.idle_minutes.'.$group->value);
    }

    public function absoluteSessionHours(): int
    {
        return $this->number('session.absolute_hours');
    }

    private function number(string $key): int
    {
        $value = $this->config->get('moashir.auth.'.$key);

        return is_numeric($value) ? (int) $value : 0;
    }
}
