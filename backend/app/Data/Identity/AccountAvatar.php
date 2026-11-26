<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\AvatarSource;
use App\Models\Files\StoredFile;

final readonly class AccountAvatar
{
    public function __construct(public StoredFile $file, public AvatarSource $source) {}
}
