<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Data\Identity\LoginOptions;
use App\Enums\Identity\SignInIdentifierType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read LoginOptions $resource
 */
final class LoginModesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'identifiers' => array_map(static fn (SignInIdentifierType $type): string => $type->value, $this->resource->identifiers),
            'entity_second_factor_enabled' => $this->resource->entitySecondFactorEnabled,
            'otp_length' => $this->resource->otpLength,
            'otp_expiry_seconds' => $this->resource->otpExpirySeconds,
            'resend_cooldown_seconds' => $this->resource->resendCooldownSeconds,
            'sso_providers' => [],
        ];
    }
}
