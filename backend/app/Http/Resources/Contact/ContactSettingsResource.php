<?php

declare(strict_types=1);

namespace App\Http\Resources\Contact;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $resource
 */
final class ContactSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'retention_months' => $this->resource,
            'retention_limits' => [
                'min' => config()->integer('moashir.contact.retention_months_min'),
                'max' => config()->integer('moashir.contact.retention_months_max'),
            ],
        ];
    }
}
