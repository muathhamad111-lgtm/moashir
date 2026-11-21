<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\ValueObjects\NationalId;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SaudiNationalId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! NationalId::isValid(NationalId::normalize($value))) {
            $fail('validation.custom.national_id.invalid')->translate();
        }
    }
}
