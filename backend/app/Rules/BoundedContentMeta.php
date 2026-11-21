<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class BoundedContentMeta implements ValidationRule
{
    private const int MAX_DEPTH = 5;

    private const int MAX_STRING = 2000;

    private const string KEY_PATTERN = '/^[A-Za-z][A-Za-z0-9_]{0,39}$/';

    public function __construct(private int $maxBytes) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            $fail('validation.custom.meta.object')->translate();

            return;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

        if ($encoded === false || strlen($encoded) > $this->maxBytes || ! $this->isWellFormed($value, 1)) {
            $fail('validation.custom.meta.bounded')->translate(['max' => (string) intdiv($this->maxBytes, 1024)]);
        }
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    private function isWellFormed(array $node, int $depth): bool
    {
        if ($depth > self::MAX_DEPTH) {
            return false;
        }

        foreach ($node as $key => $child) {
            if (is_string($key) && preg_match(self::KEY_PATTERN, $key) !== 1) {
                return false;
            }

            if (! $this->isAllowedValue($child, $depth)) {
                return false;
            }
        }

        return true;
    }

    private function isAllowedValue(mixed $value, int $depth): bool
    {
        return match (true) {
            is_array($value) => $this->isWellFormed($value, $depth + 1),
            is_string($value) => mb_strlen($value) <= self::MAX_STRING,
            default => $value === null || is_bool($value) || is_int($value) || is_float($value),
        };
    }
}
