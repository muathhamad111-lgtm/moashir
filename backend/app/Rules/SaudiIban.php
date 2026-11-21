<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\ValueObjects\Iban;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SaudiIban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = is_string($value) ? Iban::normalize($value) : '';

        if (! Iban::hasSaudiFormat($normalized)) {
            $fail('validation.custom.iban.format')->translate();

            return;
        }

        if (! Iban::hasValidChecksum($normalized)) {
            $fail('validation.custom.iban.checksum')->translate();
        }
    }
}
