<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Data\Entities\EmailVerificationToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read EmailVerificationToken $resource
 */
final class EmailVerificationTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'email_verification_token' => $this->resource->token,
            'expires_at' => $this->resource->expiresAt->toIso8601String(),
        ];
    }
}
