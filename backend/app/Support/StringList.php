<?php

declare(strict_types=1);

namespace App\Support;

final class StringList
{
    /**
     * @return list<string>
     */
    public static function from(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, is_string(...)));
    }
}
