<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Identity\PasswordHistory;
use App\Models\Identity\User;
use Closure;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class NotRecentlyUsedPassword implements ValidationRule
{
    public function __construct(private User $user, private int $depth, private Hasher $hasher) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        foreach ($this->recentHashes() as $hash) {
            if ($this->hasher->check($value, $hash)) {
                $fail('validation.custom.password.reused')->translate();

                return;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function recentHashes(): array
    {
        $history = $this->user->passwordHistories()
            ->latest('created_at')
            ->latest('id')
            ->limit($this->depth)
            ->get()
            ->map(static fn (PasswordHistory $entry): string => $entry->password_hash)
            ->all();

        return array_values(array_filter([$this->user->password ?? '', ...$history], static fn (string $hash): bool => $hash !== ''));
    }
}
