<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Data\Notifications\NotificationType;
use App\Enums\Notifications\NotificationAudience;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Support\Notifications\NotificationTypeDefinitions as Definitions;
use LogicException;

final class NotificationTypeCatalog
{
    /**
     * @return list<NotificationType>
     */
    public function all(): array
    {
        return array_map($this->of(...), NotificationCode::cases());
    }

    public function of(NotificationCode $code): NotificationType
    {
        $definition = Definitions::TYPES[$code->value] ?? throw new LogicException("Notification type {$code->value} has no definition.");

        return new NotificationType(
            $code,
            array_map(NotificationChannel::fromLetter(...), str_split($definition[Definitions::CHANNELS])),
            $definition[Definitions::AUDIENCES],
            $definition[Definitions::MANDATORY],
        );
    }

    /**
     * @param  list<NotificationAudience>  $audiences
     * @return list<NotificationType>
     */
    public function receivableBy(array $audiences): array
    {
        return array_values(array_filter($this->all(), static fn (NotificationType $type): bool => $type->reaches($audiences)));
    }
}
