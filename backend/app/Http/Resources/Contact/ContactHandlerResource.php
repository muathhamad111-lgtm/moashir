<?php

declare(strict_types=1);

namespace App\Http\Resources\Contact;

use App\Models\Identity\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read User $resource
 */
final class ContactHandlerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'name' => $this->resource->name];
    }
}
