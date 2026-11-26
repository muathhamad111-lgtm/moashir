<?php

declare(strict_types=1);

namespace App\Data\Identity;

final readonly class PhoneIndexRebuild
{
    public function __construct(public int $indexed, public int $unreadable) {}
}
