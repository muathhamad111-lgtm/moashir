<?php

declare(strict_types=1);

namespace App\Rules;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class EnumValueList implements ValidationRule
{
    private const string SEPARATOR = ',';

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public function __construct(private string $enum) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->listsKnownValuesOnly($value)) {
            $fail('validation.enum')->translate();
        }
    }

    /**
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return list<TEnum>
     */
    public static function parse(?string $list, string $enum): array
    {
        $cases = [];

        foreach (self::itemsOf($list ?? '') as $item) {
            $case = $enum::tryFrom($item);

            if ($case instanceof BackedEnum) {
                $cases[(string) $case->value] = $case;
            }
        }

        return array_values($cases);
    }

    private function listsKnownValuesOnly(string $value): bool
    {
        $items = self::itemsOf($value);

        return $items !== [] && array_all($items, fn (string $item): bool => $item !== '' && ($this->enum)::tryFrom($item) instanceof BackedEnum);
    }

    /**
     * @return list<string>
     */
    private static function itemsOf(string $list): array
    {
        return array_map(trim(...), explode(self::SEPARATOR, $list));
    }
}
