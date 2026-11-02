<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Data\Identity\OtpChallenge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read OtpChallenge $resource
 */
final class OtpChallengeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'challenge_id' => $this->resource->challengeId,
            'channel' => $this->resource->channel->value,
            'length' => $this->resource->length,
            'expires_at' => $this->resource->expiresAt->toIso8601String(),
            'resend_available_at' => $this->resource->resendAvailableAt->toIso8601String(),
        ];
    }
}
