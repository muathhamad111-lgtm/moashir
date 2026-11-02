<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\AccreditedCurrent;
use App\Models\Assessments\Assessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AccreditedCurrent $resource
 */
final class AccreditedCurrentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->resource->awaitingPayment;

        return [
            'awaiting_payment' => $payment === null ? null : [
                'payment_id' => $payment->public_id,
                'reference' => $payment->reference,
                'status' => $payment->status->value,
                'expires_at' => $payment->expires_at->toIso8601String(),
            ],
            'active' => $this->present($this->resource->active, $request),
            'latest_closed' => $this->present($this->resource->latestClosed, $request),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function present(?Assessment $assessment, Request $request): ?array
    {
        return $assessment instanceof Assessment ? new AssessmentResource($assessment)->toArray($request) : null;
    }
}
