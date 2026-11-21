<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\Identity\PasswordResetLink;
use App\Models\Identity\User;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Issues the single-use reset link token. Only its keyed hash is stored, and a user holds at most one live link.
 */
final readonly class PasswordResetTokens
{
    private const int TOKEN_LENGTH = 64;

    public function __construct(private SecretHasher $hasher, private Repository $config) {}

    public function issue(User $user): string
    {
        $token = Str::random(self::TOKEN_LENGTH);
        $now = Date::now();

        PasswordResetLink::query()->upsert(
            [['user_id' => $user->id, 'token_hash' => $this->hasher->hash($token), 'expires_at' => $now->copy()->addMinutes($this->validMinutes()), 'created_at' => $now]],
            ['user_id'],
            ['token_hash', 'expires_at', 'created_at'],
        );

        return $token;
    }

    public function wasRecentlyIssued(User $user): bool
    {
        return PasswordResetLink::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>', Date::now()->subSeconds($this->config->integer('auth.passwords.users.throttle')))
            ->exists();
    }

    public function validMinutes(): int
    {
        return $this->config->integer('auth.passwords.users.expire');
    }
}
