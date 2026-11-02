<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Notifications\NotificationCode;
use App\Support\ValueObjects\LocalizedText;

final readonly class EntityNotice
{
    public const string FOR_ENTITY = 'entity';

    public const string FOR_STAFF = 'staff';

    public function __construct(
        public NotificationCode $code,
        public LocalizedText $title,
        public LocalizedText $body,
        public ?string $link,
        public bool $viaMail,
        public bool $viaInApp,
    ) {}

    /**
     * @param  array<string, LocalizedText|string>  $replacements
     */
    public static function for(NotificationCode $code, string $audience, array $replacements, ?string $link): self
    {
        return new self(
            $code,
            LocalizedText::fromTranslationWith(self::key($code, $audience, 'title'), $replacements),
            LocalizedText::fromTranslationWith(self::key($code, $audience, 'body'), $replacements),
            $link,
            true,
            true,
        );
    }

    public function inAppOnly(): self
    {
        return new self($this->code, $this->title, $this->body, $this->link, false, true);
    }

    public function mailOnly(): self
    {
        return new self($this->code, $this->title, $this->body, $this->link, true, false);
    }

    private static function key(NotificationCode $code, string $audience, string $part): string
    {
        return 'notifications.entities.'.$code->value.'.'.$audience.'.'.$part;
    }
}
