<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\Data\Notifications\NotificationType;
use App\Enums\Notifications\NotificationAudience;
use App\Enums\Notifications\NotificationAudienceKind;
use App\Enums\Notifications\NotificationChannel;
use App\Services\Notifications\SystemNotificationChannels;
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
            'is_core' => $type->isCore,
            'audience_kinds' => array_map(static fn (NotificationAudienceKind $kind): string => $kind->value, $type->audienceKinds()),
            'channel_states' => $this->channelStates($type),
            'user_configurable_channels' => array_map(static fn (NotificationChannel $channel): string => $channel->value, $type->configurableChannels()),
            'enabled_channels' => array_map(static fn (NotificationChannel $channel): string => $channel->value, app(SystemNotificationChannels::class)->enabledOf($type)),
        ];
    }

    /**
     * Whether each channel sends, per audience the type reaches: {beneficiary: {email: true, …}, …}.
     *
     * @return array<string, array<string, bool>>
     */
    private function channelStates(NotificationType $type): array
    {
        $channels = app(SystemNotificationChannels::class);
        $states = [];
        foreach ($type->audienceKinds() as $audience) {
            foreach (NotificationChannel::cases() as $channel) {
                $states[$audience->value][$channel->value] = $channels->isEnabled($type, $channel, $audience);
            }
        }

        return $states;
    }
}
