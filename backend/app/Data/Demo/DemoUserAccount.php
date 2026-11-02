<?php

declare(strict_types=1);

namespace App\Data\Demo;

use App\Models\Identity\User;

final readonly class DemoUserAccount
{
    public function __construct(public User $user, public DemoAccount $account) {}
}
