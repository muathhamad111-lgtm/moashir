<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class FontAwesomeIcon implements ValidationRule
{
    private const string PATTERN = '/^fa-(solid|regular|light|thin|duotone|brands|sharp-solid|sharp-regular|sharp-light) fa-[a-z0-9]+(-[a-z0-9]+)*$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) > 60 || preg_match(self::PATTERN, $value) !== 1) {
            $fail('validation.custom.icon.font_awesome')->translate();
        }
    }
}
