<?php

declare(strict_types=1);

namespace App\Contracts\Identity;

/**
 * A stored one-time code whose attempts are counted and which closes once consumed or voided.
 * Implementations persist `consumed_at`, `voided_at` and `attempts` columns.
 */
interface OneTimeCode
{
    public function isOpen(): bool;

    public function isExpired(): bool;

    public function failedAttempts(): int;

    public function codeHash(): string;
}
