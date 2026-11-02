<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum CommitteeEntityVisibility: string
{
    case NameOnly = 'name_only';
    case CodeOnly = 'code_only';
    case Full = 'full';

    public function showsName(): bool
    {
        return $this !== self::CodeOnly;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
