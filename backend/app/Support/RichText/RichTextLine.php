<?php

declare(strict_types=1);

namespace App\Support\RichText;

use App\Enums\Cms\RichTextLineKind;

final readonly class RichTextLine
{
    private const string BULLET_ITEM = '/^[-*+] +(.+)$/u';

    private const string NUMBERED_ITEM = '/^(\d{1,6})[.)] +(.+)$/u';

    public function __construct(public RichTextLineKind $kind, public string $content, public ?int $number = null) {}

    public static function of(string $line): self
    {
        if (preg_match(self::BULLET_ITEM, $line, $bullet) === 1) {
            return new self(RichTextLineKind::BulletItem, trim($bullet[1]));
        }

        if (preg_match(self::NUMBERED_ITEM, $line, $numbered) === 1) {
            return new self(RichTextLineKind::NumberedItem, trim($numbered[2]), (int) $numbered[1]);
        }

        return new self(str_starts_with($line, '|') ? RichTextLineKind::TableRow : RichTextLineKind::Text, $line);
    }
}
