<?php

declare(strict_types=1);

namespace App\Http\Resources\Contact;

use App\Data\Contact\ContactReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ContactReceipt $resource
 */
final class ContactReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...($this->resource->messageId === null ? [] : ['id' => $this->resource->messageId]),
            'reference' => $this->resource->reference,
            'created_at' => $this->resource->createdAt->toIso8601String(),
        ];
    }
}
