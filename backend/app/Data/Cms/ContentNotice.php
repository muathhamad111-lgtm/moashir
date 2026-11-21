<?php

declare(strict_types=1);

namespace App\Data\Cms;

use App\Data\Notifications\NoticeText;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Support\ValueObjects\LocalizedText;

final readonly class ContentNotice
{
    private const array RECIPIENTS = [RoleKey::ContentOfficer, RoleKey::SystemAdmin];

    /**
     * @param  list<RoleKey>  $recipientRoles
     */
    public function __construct(
        public NotificationCode $code,
        public LocalizedText $title,
        public LocalizedText $body,
        public string $link,
        public array $recipientRoles,
        public ?NoticeText $text = null,
    ) {}

    /**
     * @param  array<string, LocalizedText|string>  $replacements
     */
    public static function for(NotificationCode $code, array $replacements, string $link): self
    {
        $key = 'notifications.content.'.$code->value;

        $text = new NoticeText($key, $replacements);

        return new self($code, $text->title(), $text->body(), $link, self::RECIPIENTS, $text);
    }
}
