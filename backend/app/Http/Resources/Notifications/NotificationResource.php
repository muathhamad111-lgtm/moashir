<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\Enums\Notifications\NotificationCode;
use App\Models\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Notification $resource
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'module' => NotificationCode::tryFrom($this->resource->code)?->module(),
            'category' => $this->resource->category->value,
            'title' => $this->resource->title->toArray(),
            'body' => $this->resource->body->toArray(),
            'link' => $this->resource->link,
            'path' => $this->resource->path?->value,
            'path_label' => $this->resource->path?->label()->toArray(),
            'is_read' => $this->resource->read_at !== null,
            'read_at' => $this->resource->read_at?->toIso8601String(),
            'actioned_at' => $this->resource->actioned_at?->toIso8601String(),
            'archived_at' => $this->resource->archived_at?->toIso8601String(),
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}
