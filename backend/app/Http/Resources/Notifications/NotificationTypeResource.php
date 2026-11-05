<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\Data\Notifications\NotificationType;
use App\Enums\Notifications\NotificationAudience;
use App\Enums\Notifications\NotificationChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read NotificationType $resource
 */
final class NotificationTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->resource;

        return [
            'code' => $type->code->value,
            'module' => $type->code->module(),
            'category' => $type->code->category()->value,
            'channels' => array_map(static fn (NotificationChannel $channel): string => $channel->value, $type->channels),
            'audiences' => array_map(static fn (NotificationAudience $audience): string => $audience->value, $type->audiences),
            'is_mandatory' => $type->isMandatory,
            'user_configurable_channels' => array_map(static fn (NotificationChannel $channel): string => $channel->value, $type->configurableChannels()),
        ];
    }
}
