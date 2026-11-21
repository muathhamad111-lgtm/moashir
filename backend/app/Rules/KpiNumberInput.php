<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\ValueObjects\KpiNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class KpiNumberInput implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! KpiNumber::tryFrom($value) instanceof KpiNumber) {
            $fail('validation.custom.kpi_number.format')->translate();
        }
    }
}
