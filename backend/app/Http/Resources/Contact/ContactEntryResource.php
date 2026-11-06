<?php

declare(strict_types=1);

namespace App\Http\Resources\Contact;

use App\Models\Contact\ContactMessageEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ContactMessageEntry $resource
 */
final class ContactEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'kind' => $this->resource->kind->value,
            'body' => $this->resource->body,
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}
