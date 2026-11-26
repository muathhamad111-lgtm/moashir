<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class HttpsLinkUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! SafeLinkUrl::isHttps($value)) {
            $fail('validation.custom.link.https')->translate();
        }
    }
}
