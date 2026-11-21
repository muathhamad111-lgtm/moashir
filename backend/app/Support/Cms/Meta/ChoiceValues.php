<?php

declare(strict_types=1);

namespace App\Support\Cms\Meta;

use BackedEnum;

final class ChoiceValues
{
    /**
     * @param  list<BackedEnum>  $choices
     * @return list<string>
     */
    public static function of(array $choices): array
    {
        return array_map(static fn (BackedEnum $choice): string => (string) $choice->value, $choices);
    }
}
