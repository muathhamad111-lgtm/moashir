<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\ValueObjects\SignInIdentifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class RecognisedSignInIdentifier implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! SignInIdentifier::tryParse($value) instanceof SignInIdentifier) {
            $fail('validation.custom.identifier.format')->translate();
        }
    }
}
