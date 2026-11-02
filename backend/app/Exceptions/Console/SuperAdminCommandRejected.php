<?php

declare(strict_types=1);

namespace App\Exceptions\Console;

use RuntimeException;

final class SuperAdminCommandRejected extends RuntimeException
{
    public static function outsideConsole(): self
    {
        return new self('The Super Admin role can only be managed from an interactive console session.');
    }

    public static function userNotFound(): self
    {
        return new self('No user exists with this email address.');
    }

    public static function userInactive(): self
    {
        return new self('The user account is not active.');
    }

    public static function holdsOtherRoles(): self
    {
        return new self('The user holds other roles. Remove them before granting the Super Admin role.');
    }

    public static function entityUser(): self
    {
        return new self('Entity accounts cannot become Super Admin.');
    }

    public static function limitReached(int $maximum): self
    {
        return new self(sprintf('The maximum number of Super Admin accounts (%d) has been reached.', $maximum));
    }

    public static function alreadySuperAdmin(): self
    {
        return new self('The user is already a Super Admin.');
    }

    public static function notSuperAdmin(): self
    {
        return new self('The user is not a Super Admin.');
    }

    public static function lastAccount(): self
    {
        return new self('This is the last Super Admin account. Use --allow-last to revoke it.');
    }

    public static function missingEmail(): self
    {
        return new self('Provide --email or set SUPER_ADMIN_INITIAL_EMAIL.');
    }

    public static function missingName(): self
    {
        return new self('Provide --name to create the initial Super Admin account.');
    }

    public static function notConfirmed(): self
    {
        return new self('The confirmation did not match. Nothing was changed.');
    }
}
