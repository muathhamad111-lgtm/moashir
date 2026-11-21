<?php

declare(strict_types=1);

namespace App\Data\Platform;

use App\Models\Identity\User;

final readonly class ReadinessViewer
{
    public function __construct(public ?string $ip, public ?User $user) {}
}
