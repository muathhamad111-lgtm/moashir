<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class FreeOfHtmlMarkup implements ValidationRule
{
    private const string MARKUP = '/<\s*(?:[\/!?]|[a-z])/i';

    public static function contains(string $text): bool
    {
        return preg_match(self::MARKUP, $text) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::contains($value)) {
            $fail('validation.custom.rich_text.markup')->translate();
        }
    }
}
