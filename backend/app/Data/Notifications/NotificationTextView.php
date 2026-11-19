<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Models\Notifications\NotificationTextOverride;
use App\Support\Notifications\Templates\NotificationTextTemplate;

final readonly class NotificationTextView
{
    public const string SMS_PART = 'sms';

    /**
     * @param  list<string>  $requiredVariables
     */
    public function __construct(
        public NotificationTextTemplate $template,
        public ?NotificationTextOverride $override,
        public array $requiredVariables,
    ) {}

    /**
     * @return array{ar: string, en: string}|null
     */
    public function sms(): ?array
    {
        return $this->override?->parts[self::SMS_PART] ?? null;
    }

    /**
     * @return array{ar: string, en: string}
     */
    public function current(string $part): array
    {
        return $this->override?->parts[$part] ?? $this->template->parts[$part];
    }
}
