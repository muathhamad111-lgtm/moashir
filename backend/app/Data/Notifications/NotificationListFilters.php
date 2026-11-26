<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Notifications\NotificationCategory;
use App\Enums\Notifications\NotificationCode;
use App\Support\ValueObjects\SearchTerm;

final readonly class NotificationListFilters
{
    public function __construct(
        public ?NotificationCategory $category,
        public ?bool $isRead,
        public bool $archived,
        public ?NotificationCode $code,
        public ?SearchTerm $search,
        public int $perPage,
        public ?AssessmentPath $path = null,
    ) {}
}
