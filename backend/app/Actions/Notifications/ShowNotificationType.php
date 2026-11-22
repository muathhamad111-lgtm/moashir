<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Data\Notifications\NotificationChannelState;
use App\Data\Notifications\NotificationTextView;
use App\Data\Notifications\NotificationType;
use App\Data\Notifications\NotificationTypeTexts;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Models\Notifications\NotificationTextOverride;
use App\Services\Notifications\NotificationTypeCatalog;
use App\Services\Notifications\SystemNotificationChannels;
use App\Services\Notifications\Templates\ShippedNotificationTemplates;
use App\Support\Notifications\Templates\NotificationTextTemplate;

final readonly class ShowNotificationType
{
    public function __construct(
        private NotificationTypeCatalog $types,
        private ShippedNotificationTemplates $templates,
        private SystemNotificationChannels $channels,
    ) {}

    public function handle(NotificationCode $code): NotificationTypeTexts
    {
        $type = $this->types->of($code);
        $overrides = NotificationTextOverride::query()->where('code', $code)->get()->keyBy('template_key');

        $texts = array_map(
            static fn (NotificationTextTemplate $template): NotificationTextView => new NotificationTextView(
                $template,
                $overrides->get($template->key),
                $type->isCore ? $template->placeholders : [],
            ),
            $this->templates->catalog()->of($code),
        );

        return new NotificationTypeTexts($type, $texts, $this->channelStates($type));
    }

    /**
     * @return list<NotificationChannelState>
     */
    private function channelStates(NotificationType $type): array
    {
        $states = [];
        foreach ($type->audienceKinds() as $audience) {
            foreach (NotificationChannel::cases() as $channel) {
                $states[] = new NotificationChannelState(
                    $channel,
                    $audience,
                    $this->channels->isEnabled($type, $channel, $audience),
                    in_array($channel, $type->channels, true),
                    $this->channels->isSwitchable($type, $channel),
                );
            }
        }

        return $states;
    }
}
