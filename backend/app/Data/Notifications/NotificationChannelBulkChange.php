<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\Notifications\NotificationAudienceKind;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;

/**
 * One bulk switch: turn the given channels on or off for the given audiences of the given types.
 */
final readonly class NotificationChannelBulkChange
{
    /**
     * @param  list<NotificationCode>  $codes
     * @param  list<NotificationChannel>  $channels
     * @param  list<NotificationAudienceKind>  $audiences
     */
    public function __construct(
        public array $codes,
        public array $channels,
        public array $audiences,
        public bool $enabled,
    ) {}
}
