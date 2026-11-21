<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class PriceOrFreeBeforeVat implements ValidationRule
{
    private const string PATTERN = '/^\d{1,7}\.\d{2}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('validation.custom.price.format')->translate();
        }
    }
}
