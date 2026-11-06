<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Data\Notifications\NotificationType;
use App\Data\Notifications\NotificationTypeFilters;
use App\Enums\Notifications\NotificationAudience;
use App\Enums\Notifications\NotificationCategory;
use App\Services\Notifications\NotificationTypeCatalog;

final readonly class ListNotificationTypes
{
    public function __construct(private NotificationTypeCatalog $catalog) {}

    /**
     * @return list<NotificationType>
     */
    public function handle(NotificationTypeFilters $filters): array
    {
        return array_values(array_filter(
            $this->catalog->all(),
            static fn (NotificationType $type): bool => ($filters->module === null || $type->code->module() === $filters->module)
                && (! $filters->category instanceof NotificationCategory || $type->code->category() === $filters->category)
                && (! $filters->audience instanceof NotificationAudience || $type->reaches([$filters->audience])),
        ));
    }
}
