<?php

declare(strict_types=1);

namespace App\Http\Resources\Contact;

use App\Models\Contact\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the staff inbox list: the message card without the sender's e-mail and mobile, which
 * only the message page shows (PDPL data minimisation).
 *
 * @property-read ContactMessage $resource
 */
final class ContactInboxRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...new ContactMessageResource($this->resource)->toArray($request),
            'sender' => ContactMessageResource::listedSender($this->resource),
        ];
    }
}
