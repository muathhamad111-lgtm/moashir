<?php

declare(strict_types=1);

namespace App\Rules;

use App\Contracts\BreachedPasswordChecker;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class NotBreachedPassword implements ValidationRule
{
    public function __construct(private BreachedPasswordChecker $checker) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && $this->checker->isBreached($value)) {
            $fail('validation.custom.password.breached')->translate();
        }
    }
}
