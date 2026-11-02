<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final readonly class SecretInput implements ValidationRule
{
    public function __construct(
        private int $minimumLength,
        private int $maximumLength,
        private ?string $prefix = null,
        private bool $allowsWhitespace = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value)) {
            if ($value !== ['clear' => true]) {
                $fail('validation.custom.secret.clear')->translate();
            }

            return;
        }

        if (! is_string($value)) {
            $fail('validation.custom.secret.format')->translate();

            return;
        }

        if ($value === '') {
            return;
        }

        $this->validateText($value, $fail);
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function validateText(string $value, Closure $fail): void
    {
        $length = mb_strlen($value);

        if ($length < $this->minimumLength || $length > $this->maximumLength) {
            $fail('validation.custom.secret.length')->translate(['min' => (string) $this->minimumLength, 'max' => (string) $this->maximumLength]);

            return;
        }

        if ($this->prefix !== null && ! str_starts_with($value, $this->prefix)) {
            $fail('validation.custom.secret.prefix')->translate(['prefix' => $this->prefix]);

            return;
        }

        $pattern = $this->allowsWhitespace ? '/[\x00-\x08\x0A-\x1F\x7F]/' : '/[\s\x00-\x1F\x7F]/';

        if (preg_match($pattern, $value) === 1) {
            $fail('validation.custom.secret.format')->translate();
        }
    }
}
