<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\ValueObjects\SearchTerm;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class SearchableTerm implements ValidationRule
{
    public function __construct(private int $minimumLength) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $term = SearchTerm::tryFrom($value);

        if (! $term instanceof SearchTerm || $term->length() < $this->minimumLength) {
            $fail('validation.custom.q.searchable')->translate(['min' => (string) $this->minimumLength]);
        }
    }
}
