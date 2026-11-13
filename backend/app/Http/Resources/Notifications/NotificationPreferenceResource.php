<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\Data\Notifications\ChannelPreference;
use App\Data\Notifications\NotificationPreferenceRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read NotificationPreferenceRow $resource
 */
final class NotificationPreferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->resource->type;

        return [
            'code' => $type->code->value,
            'module' => $type->code->module(),
            'category' => $type->code->category()->value,
            'is_mandatory' => $type->isMandatory,
            'channels' => array_map(static fn (ChannelPreference $preference): array => [
                'channel' => $preference->channel->value,
                'enabled' => $preference->enabled,
                'configurable' => $preference->configurable,
            ], $this->resource->channels),
        ];
    }
}
