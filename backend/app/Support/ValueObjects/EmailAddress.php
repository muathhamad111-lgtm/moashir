<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class EmailAddress
{
    public string $value;

    public function __construct(string $email)
    {
        $normalized = mb_strtolower(trim($email));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        $this->value = $normalized;
    }

    public function masked(): string
    {
        [$local, $domain] = explode('@', $this->value, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
