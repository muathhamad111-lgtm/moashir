<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\ValueObjects\VatRate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final readonly class VatRatePercent implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.custom.vat_rate.format')->translate();

            return;
        }

        try {
            new VatRate($value);
        } catch (InvalidArgumentException) {
            $fail('validation.custom.vat_rate.format')->translate();
        }
    }
}
