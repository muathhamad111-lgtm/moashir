<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Models\Finance\CheckoutIntent;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CheckoutIntent $resource
 */
final class AbandonedCartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $intent = $this->resource;
        $entity = $intent->entity;
        $email = $entity->owner?->email;

        return [
            'id' => $intent->id,
            'entity' => ['id' => $entity->id, 'reference' => $entity->reference(), 'name' => $entity->name->toArray()],
            'email' => $email === null ? null : new EmailAddress($email)->masked(),
            'marketing_consent' => $entity->marketing_consent_at !== null && $entity->marketing_unsubscribed_at === null,
            'purpose' => $intent->purpose->value,
            'started_at' => $intent->created_at->toIso8601String(),
            'last_seen_at' => $intent->last_seen_at->toIso8601String(),
            'contacted_at' => $intent->contacted_at?->toIso8601String(),
        ];
    }
}
