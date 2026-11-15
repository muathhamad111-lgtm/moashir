<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Models\Finance\PaymentEvent;
use App\Support\Finance\PaymentPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PaymentEvent $resource
 */
final class TransactionEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->resource;

        return [
            'id' => $event->id,
            'created_at' => $event->created_at->toIso8601String(),
            'source' => $event->source->value,
            'type' => $event->event_type,
            'result' => $event->processing_result?->value,
            'mode' => $event->mode?->value,
            'provider_reference' => PaymentPresenter::maskedReference($event->provider_payment_id),
        ];
    }
}
