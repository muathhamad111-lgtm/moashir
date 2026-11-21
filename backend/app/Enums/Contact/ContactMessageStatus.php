<?php

declare(strict_types=1);

namespace App\Enums\Contact;

enum ContactMessageStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Replied = 'replied';
    case Closed = 'closed';

    public function isOpen(): bool
    {
        return $this !== self::Closed;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
