<?php

declare(strict_types=1);

namespace App\Data\Mail;

use App\Data\Notifications\NoticeText;
use App\Enums\Notifications\NotificationCode;
use App\Support\ValueObjects\LocalizedText;

/**
 * What a notice e-mail needs from any notice (entity, criteria or content): its code, wording, link and the
 * template it came from, so the optional summary line and button label can be read from it, and the blocks it
 * adds (items, steps, seal).
 */
final readonly class MailNotice
{
    public function __construct(
        public NotificationCode $code,
        public LocalizedText $title,
        public LocalizedText $body,
        public ?string $link,
        public ?NoticeText $text,
        public MailBlocks $blocks = new MailBlocks,
    ) {}
}
