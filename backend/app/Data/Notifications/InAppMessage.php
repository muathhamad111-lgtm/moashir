<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Notifications\NotificationCode;
use App\Support\ValueObjects\LocalizedText;

final readonly class InAppMessage
{
    public function __construct(
        public NotificationCode $code,
        public LocalizedText $title,
        public LocalizedText $body,
        public ?string $link = null,
        public ?AssessmentPath $path = null,
    ) {}
}
