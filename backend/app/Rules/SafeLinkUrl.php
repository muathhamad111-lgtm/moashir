<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SafeLinkUrl implements ValidationRule
{
    private const string INTERNAL_PATH = '#^/(?!/)[A-Za-z0-9\-._~/?=&%\#]*$#';

    private const string ANCHOR = '/^#[A-Za-z][A-Za-z0-9_-]*$/';

    private const string MAIL = '/^mailto:[^\s@<>"]+@[^\s@<>"]+$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isSafe($value)) {
            $fail('validation.custom.link.safe')->translate();
        }
    }

    public static function isSafe(string $value): bool
    {
        return preg_match(self::INTERNAL_PATH, $value) === 1
            || preg_match(self::ANCHOR, $value) === 1
            || preg_match(self::MAIL, $value) === 1
            || self::isHttps($value);
    }

    private static function isHttps(string $value): bool
    {
        return str_starts_with($value, 'https://')
            && filter_var($value, FILTER_VALIDATE_URL) !== false
            && ! preg_match('/[\s<>"]/', $value);
    }
}
