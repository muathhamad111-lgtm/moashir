<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Identity\NationalIdProblem;
use App\Support\ValueObjects\NationalId;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SaudiNationalId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(NationalIdProblem::NotDigits->messageKey())->translate();

            return;
        }

        $problem = NationalId::problemOf(NationalId::normalize($value));

        if ($problem instanceof NationalIdProblem) {
            $fail($problem->messageKey())->translate();
        }
    }
}
