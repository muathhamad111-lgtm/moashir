<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Enums\Entities\ProfileChangeFile;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\ProfileChanges\OwnProfileFileChanges;

final readonly class RemoveOwnEntityLogo
{
    public function __construct(private OwnProfileFileChanges $changes) {}

    public function handle(User $owner): Entity
    {
        return $this->changes->proposeRemoval($owner, ProfileChangeFile::Logo);
    }
}
