<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Contracts\BreachedPasswordChecker;
use App\Models\Identity\User;
use App\Rules\NotBreachedPassword;
use App\Rules\NotRecentlyUsedPassword;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class PasswordPolicy
{
    public function __construct(
        private Repository $config,
        private BreachedPasswordChecker $checker,
        private Hasher $hasher,
    ) {}

    /**
     * @return list<string|ValidationRule>
     */
    public function rules(?User $user = null): array
    {
        $rules = [
            'required',
            'string',
            'min:'.$this->limit('min_length'),
            'max:'.$this->limit('max_length'),
            'confirmed',
            new NotBreachedPassword($this->checker),
        ];

        if ($user instanceof User) {
            $rules[] = new NotRecentlyUsedPassword($user, $this->limit('history'), $this->hasher);
        }

        return $rules;
    }

    private function limit(string $key): int
    {
        $value = $this->config->get('moashir.auth.password.'.$key);

        return is_numeric($value) ? (int) $value : 0;
    }
}
