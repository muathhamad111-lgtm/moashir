<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Integrations\SmtpHostGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class Hostname implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! SmtpHostGuard::isHostname($value)) {
            $fail('validation.custom.host.hostname')->translate();
        }
    }
}
