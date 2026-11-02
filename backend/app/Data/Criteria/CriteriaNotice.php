<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Support\ValueObjects\LocalizedText;

final readonly class CriteriaNotice
{
    /**
     * @param  list<RoleKey>  $recipientRoles
     */
    public function __construct(
        public NotificationCode $code,
        public LocalizedText $title,
        public LocalizedText $body,
        public ?string $link,
        public array $recipientRoles,
    ) {}

    /**
     * @param  array<string, LocalizedText|string>  $replacements
     * @param  list<RoleKey>  $recipientRoles
     */
    public static function for(NotificationCode $code, array $replacements, ?string $link, array $recipientRoles): self
    {
        $key = 'notifications.criteria.'.$code->value;

        return new self(
            $code,
            LocalizedText::fromTranslationWith($key.'.title', $replacements),
            LocalizedText::fromTranslationWith($key.'.body', $replacements),
            $link,
            $recipientRoles,
        );
    }
}
