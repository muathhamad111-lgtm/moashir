<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\IdentityChangeSource;
use App\Models\Identity\User;

/**
 * Who saves a national ID directly (without an approval request) and through which path.
 */
final readonly class NationalIdEntry
{
    public function __construct(public User $actor, public IdentityChangeSource $source) {}
}
