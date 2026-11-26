<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\Notifications\NotificationAudience;
use App\Enums\Notifications\NotificationCategory;

final readonly class NotificationTypeFilters
{
    public function __construct(
        public ?string $module,
        public ?NotificationCategory $category,
        public ?NotificationAudience $audience,
    ) {}
}
