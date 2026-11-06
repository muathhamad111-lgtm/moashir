<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\ValueObjects\WebsiteUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class WebsiteAddress implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! WebsiteUrl::tryFrom($value) instanceof WebsiteUrl) {
            $fail('validation.custom.website_url.https')->translate();
        }
    }
}
