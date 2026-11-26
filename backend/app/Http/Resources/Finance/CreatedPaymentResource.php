<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Data\Finance\CreatedPayment;
use App\Data\Finance\OwnPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CreatedPayment $resource
 */
final class CreatedPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...new OwnPaymentResource(new OwnPayment($this->resource->payment))->toArray($request),
            'checkout' => $this->resource->checkout?->toArray(),
        ];
    }
}
