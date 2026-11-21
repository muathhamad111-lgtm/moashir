<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Exceptions\Identity\AccountActionRejected;
use App\Models\Identity\PasswordResetLink;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds a reset link by its token alone. A missing, expired or already used link all answer the same way.
 */
final readonly class UsablePasswordResetLinkFinder
{
    public function __construct(private SecretHasher $hasher) {}

    public function find(string $token): PasswordResetLink
    {
        return $this->usable($this->byToken($token)->first());
    }

    public function findForUpdate(string $token): PasswordResetLink
    {
        return $this->usable($this->byToken($token)->lockForUpdate()->first());
    }

    public function tryFind(string $token): ?PasswordResetLink
    {
        $link = $this->byToken($token)->first();

        return $link instanceof PasswordResetLink && ! $link->isExpired() ? $link : null;
    }

    /**
     * @return Builder<PasswordResetLink>
     */
    private function byToken(string $token): Builder
    {
        return PasswordResetLink::query()->with('user')->where('token_hash', $this->hasher->hash($token));
    }

    private function usable(?PasswordResetLink $link): PasswordResetLink
    {
        if (! $link instanceof PasswordResetLink || $link->isExpired()) {
            throw AccountActionRejected::invalidToken();
        }

        return $link;
    }
}
