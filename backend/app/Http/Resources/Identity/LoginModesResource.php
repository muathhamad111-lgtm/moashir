<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Services\Identity\AuthSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AuthSettings $resource
 */
final class LoginModesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'email_password' => $this->resource->isEmailPasswordEnabled(),
            'email_otp' => $this->resource->isEmailOtpEnabled(),
            'sms_otp' => $this->resource->isSmsOtpEnabled(),
            'otp_length' => $this->resource->otpLength(),
            'otp_expiry_seconds' => $this->resource->otpExpirySeconds(),
            'resend_cooldown_seconds' => $this->resource->otpResendCooldownSeconds(),
            'sso_providers' => [],
        ];
    }
}
