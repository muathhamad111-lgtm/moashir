<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\Notifications\NotificationAudience;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;

final readonly class NotificationType
{
    /**
     * @param  list<NotificationChannel>  $channels
     * @param  list<NotificationAudience>  $audiences
     */
    public function __construct(
        public NotificationCode $code,
        public array $channels,
        public array $audiences,
        public bool $isMandatory,
    ) {}

    /**
     * @return list<NotificationChannel>
     */
    public function configurableChannels(): array
    {
        if ($this->isMandatory) {
            return [];
        }

        return array_values(array_filter($this->channels, static fn (NotificationChannel $channel): bool => $channel->isOptional()));
    }

    public function allowsOptOut(NotificationChannel $channel): bool
    {
        return in_array($channel, $this->configurableChannels(), true);
    }

    /**
     * @param  list<NotificationAudience>  $audiences
     */
    public function reaches(array $audiences): bool
    {
        return array_intersect(
            array_map(static fn (NotificationAudience $audience): string => $audience->value, $this->audiences),
            array_map(static fn (NotificationAudience $audience): string => $audience->value, $audiences),
        ) !== [];
    }
}
