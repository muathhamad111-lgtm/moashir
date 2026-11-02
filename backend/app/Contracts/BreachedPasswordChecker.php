<?php

declare(strict_types=1);

namespace App\Contracts;

interface BreachedPasswordChecker
{
    public function isBreached(string $password): bool;
}
