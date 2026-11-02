<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\Identity\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use LogicException;

final readonly class PasswordResetTokens
{
    public function __construct(private PasswordBrokerFactory $brokers) {}

    public function issue(User $user): string
    {
        return $this->broker()->createToken($user);
    }

    public function wasRecentlyIssued(User $user): bool
    {
        return $this->broker()->getRepository()->recentlyCreatedToken($user);
    }

    public function isValid(User $user, string $token): bool
    {
        return $this->broker()->tokenExists($user, $token);
    }

    public function revoke(User $user): void
    {
        $this->broker()->deleteToken($user);
    }

    private function broker(): PasswordBroker
    {
        $broker = $this->brokers->broker();

        return $broker instanceof PasswordBroker ? $broker : throw new LogicException('The password broker must be the database token broker.');
    }
}
