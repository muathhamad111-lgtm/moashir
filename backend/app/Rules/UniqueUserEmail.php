<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Identity\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class UniqueUserEmail implements ValidationRule
{
    public function __construct(private ?int $ignoredUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $exists = User::query()
            ->withTrashed()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($value))])
            ->when($this->ignoredUserId !== null, fn ($query) => $query->whereKeyNot($this->ignoredUserId))
            ->exists();

        if ($exists) {
            $fail('validation.unique')->translate();
        }
    }
}
