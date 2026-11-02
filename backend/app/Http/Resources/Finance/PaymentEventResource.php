<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Models\Finance\Payment;
use App\Models\Finance\PaymentEvent;
use App\Support\Finance\PaymentPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PaymentEvent $resource
 */
final class PaymentEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->resource;

        return [
            'id' => $event->id,
            'payment' => $event->payment instanceof Payment ? ['id' => $event->payment->public_id, 'reference' => $event->payment->reference] : null,
            'source' => $event->source->value,
            'event_type' => $event->event_type,
            'mode' => $event->mode?->value,
            'signature_valid' => $event->signature_valid,
            'result' => $event->processing_result?->value,
            'error_code' => $event->error_code,
            'provider_ref' => PaymentPresenter::maskedReference($event->provider_payment_id),
            'created_at' => $event->created_at->toIso8601String(),
            'processed_at' => $event->processed_at?->toIso8601String(),
        ];
    }
}
